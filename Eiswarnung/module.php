<?php

class Eiswarnung extends IPSModule
{
    private const API_URL = 'https://api.eiswarnung.de/';
    private const PROFILE_FORECAST = 'Eiswarnung.Forecast';

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('APIKey', '');
        $this->RegisterPropertyFloat('Latitude', 0.0);
        $this->RegisterPropertyFloat('Longitude', 0.0);
        $this->RegisterPropertyBoolean('AutoUpdate', true);
        $this->RegisterPropertyInteger('UpdateInterval', 12);
        $this->RegisterTimer('UpdateTimer', 0, 'Eiswarnung_Update($_IPS[\'TARGET\']);');

        $this->RegisterIntegerProfile(self::PROFILE_FORECAST, 'WeatherCloudy', 0, 2, 1);
        IPS_SetVariableProfileAssociation(self::PROFILE_FORECAST, 0, 'Kein Eis', 'Sun', 0x35A853);
        IPS_SetVariableProfileAssociation(self::PROFILE_FORECAST, 1, 'Eis', 'Snow', 0xD64545);
        IPS_SetVariableProfileAssociation(self::PROFILE_FORECAST, 2, 'Eventuell Eis', 'Cloud', 0xE6A700);

        $this->RegisterVariableInteger('ForecastId', 'Eiswarnung', self::PROFILE_FORECAST, 10);
        $this->RegisterVariableString('ForecastText', 'Vorhersage', '', 20);
        $this->RegisterVariableString('ForecastCity', 'Ort', '', 30);
        $this->RegisterVariableString('ForecastDate', 'Gültig für', '', 40);
        $this->RegisterVariableString('RequestDate', 'Letzte Anfrage', '', 50);
        $this->RegisterVariableInteger('CallsLeft', 'Abfragen heute übrig', '', 60);
        $this->RegisterVariableInteger('CallsDailyLimit', 'Tageslimit', '', 70);
        $this->RegisterVariableInteger('CallsResetInSeconds', 'Kontingent erneuert in (Sekunden)', '', 80);
        $this->RegisterVariableBoolean('Success', 'Letzte Anfrage erfolgreich', '', 90);
        $this->RegisterVariableString('Status', 'Statusmeldung', '', 100);
        $this->RegisterVariableBoolean('UpdateNow', 'Jetzt aktualisieren', '~Switch', 0);
        $this->EnableAction('UpdateNow');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $hours = max(1, min(24, $this->ReadPropertyInteger('UpdateInterval')));
        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyBoolean('AutoUpdate') ? $hours * 3600000 : 0);
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident !== 'UpdateNow') {
            throw new InvalidArgumentException('Unknown ident: ' . $Ident);
        }

        if ($Value) {
            $this->UpdateForecast();
        }
        SetValue($this->GetIDForIdent('UpdateNow'), false);
    }

    public function UpdateForecast()
    {
        $key = trim($this->ReadPropertyString('APIKey'));
        $latitude = $this->ReadPropertyFloat('Latitude');
        $longitude = $this->ReadPropertyFloat('Longitude');

        if (strlen($key) !== 32 || !is_numeric($latitude) || $latitude < -90 || $latitude > 90 || !is_numeric($longitude) || $longitude < -180 || $longitude > 180) {
            $this->SetRequestStatus(false, 'Bitte einen gültigen 32-stelligen API-Key sowie gültige Koordinaten eintragen.');
            return;
        }

        $body = http_build_query([
            'key' => $key,
            'lat' => (string) $latitude,
            'lng' => (string) $longitude
        ], '', '&', PHP_QUERY_RFC3986);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => $body,
                'timeout' => 20,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents(self::API_URL, false, $context);
        if ($response === false) {
            $this->SetRequestStatus(false, 'Die API konnte nicht erreicht werden. Netzwerk- oder TLS-Verbindung prüfen.');
            return;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $this->SetRequestStatus(false, 'Die API hat keine gültige JSON-Antwort geliefert.');
            return;
        }

        if (isset($data['callsLeft'])) {
            SetValue($this->GetIDForIdent('CallsLeft'), (int) $data['callsLeft']);
        }
        if (isset($data['callsDailyLimit'])) {
            SetValue($this->GetIDForIdent('CallsDailyLimit'), (int) $data['callsDailyLimit']);
        }
        if (isset($data['callsResetInSeconds'])) {
            SetValue($this->GetIDForIdent('CallsResetInSeconds'), (int) $data['callsResetInSeconds']);
        }

        $success = isset($data['success']) && $data['success'] === true;
        if (!$success) {
            $message = isset($data['message']) ? (string) $data['message'] : 'Die API-Anfrage ist fehlgeschlagen.';
            if (isset($data['code'])) {
                $message .= ' (Code ' . (int) $data['code'] . ')';
            }
            $this->SetRequestStatus(false, $message);
            return;
        }

        $result = isset($data['result']) && is_array($data['result']) ? $data['result'] : [];
        if (!isset($result['forecastId'], $result['forecastText'], $result['forecastCity'], $result['forecastDate'])) {
            $this->SetRequestStatus(false, 'Die API-Antwort enthält keine vollständige Vorhersage.');
            return;
        }

        $forecastId = (int) $result['forecastId'];
        if ($forecastId < 0 || $forecastId > 2) {
            $this->SetRequestStatus(false, 'Die API hat einen unbekannten Vorhersagewert geliefert: ' . $forecastId);
            return;
        }

        SetValue($this->GetIDForIdent('ForecastId'), $forecastId);
        SetValue($this->GetIDForIdent('ForecastText'), (string) $result['forecastText']);
        SetValue($this->GetIDForIdent('ForecastCity'), (string) $result['forecastCity']);
        SetValue($this->GetIDForIdent('ForecastDate'), (string) $result['forecastDate']);
        if (isset($result['requestDate'])) {
            SetValue($this->GetIDForIdent('RequestDate'), (string) $result['requestDate']);
        }

        $message = isset($data['message']) ? (string) $data['message'] : 'Anfrage erfolgreich.';
        $this->SetRequestStatus(true, $message);
    }

    private function SetRequestStatus(bool $success, string $message)
    {
        SetValue($this->GetIDForIdent('Success'), $success);
        SetValue($this->GetIDForIdent('Status'), $message);
    }

    private function RegisterIntegerProfile(string $name, string $icon, int $min, int $max, int $step)
    {
        if (!IPS_VariableProfileExists($name)) {
            IPS_CreateVariableProfile($name, VARIABLETYPE_INTEGER);
        }

        $profile = IPS_GetVariableProfile($name);
        if ($profile['ProfileType'] !== VARIABLETYPE_INTEGER) {
            throw new RuntimeException('Das Variablenprofil ' . $name . ' hat nicht den Typ Integer.');
        }

        IPS_SetVariableProfileIcon($name, $icon);
        IPS_SetVariableProfileText($name, '', '');
        IPS_SetVariableProfileValues($name, $min, $max, $step);
        IPS_SetVariableProfileDigits($name, 0);
    }
}
