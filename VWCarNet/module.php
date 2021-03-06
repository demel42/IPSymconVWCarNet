<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

if (!defined('VW_MODEL_STANDARD')) {
    define('VW_MODEL_STANDARD', 0);
    define('VW_MODEL_HYBRID', 1);
    define('VW_MODEL_ELECTRIC', 2);
}

if (!defined('VW_PARKINGLIGHT_UNKNOWN')) {
    define('VW_PARKINGLIGHT_UNKNOWN', -1);
    define('VW_PARKINGLIGHT_OFF', 2);
    define('VW_PARKINGLIGHT_LEFT', 3);
    define('VW_PARKINGLIGHT_RIGHT', 4);
    define('VW_PARKINGLIGHT_BOTH', 5);
}

if (!defined('VW_PARKINGBREAK_UNKNOWN')) {
    define('VW_PARKINGBREAK_UNKNOWN', -1);
    define('VW_PARKINGBREAK_RELEASED', 0);
    define('VW_PARKINGBREAK_TIGHTENED', 1);
}

if (!defined('VW_SERVICEMESSAGE_UNKNOWN')) {
    define('VW_SERVICEMESSAGE_UNKNOWN', -1);
    define('VW_SERVICEMESSAGE_NONE', 0);
    define('VW_SERVICEMESSAGE_WARNING', 1);
}

if (!defined('VW_DOORSTATE_UNKNOWN')) {
    define('VW_DOORSTATE_UNKNOWN', -1);
    define('VW_DOORSTATE_UNSUPPORTED', 0);
    define('VW_DOORSTATE_OPENED', 2);
    define('VW_DOORSTATE_CLOSED', 3);
    define('VW_DOORSTATE_LOCKED', 4);
}

if (!defined('VW_WINDOWSTATE_UNKNOWN')) {
    define('VW_WINDOWSTATE_UNKNOWN', -1);
    define('VW_WINDOWSTATE_UNSUPPORTED', 0);
    define('VW_WINDOWSTATE_OPENED', 2);
    define('VW_WINDOWSTATE_CLOSED', 3);
}

if (!defined('VW_CHARGINGSTATE_UNKNOWN')) {
    define('VW_CHARGINGSTATE_UNKNOWN', -1);
    define('VW_CHARGINGSTATE_OFF', 0);
    define('VW_CHARGINGSTATE_CHARGING', 1);
}

define('VW_TEST', false);

if (VW_TEST) {
    if (!defined('VW_TEST_STATUS')) {
        define('VW_TEST_STATUS', '');
    }

    if (!defined('VW_TEST_POSITION')) {
        define('VW_TEST_POSITION', '');
    }

    if (!defined('VW_TEST_CLIMATER')) {
        define('VW_TEST_CLIMATER', '');
    }

    if (!defined('VW_TEST_CHARGER')) {
        define('VW_TEST_CHARGER', '');
    }
}

class VWCarNet extends IPSModule
{
    use VWCarNetCommon;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('username', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('vin', '');

        $this->RegisterPropertyInteger('model', VW_MODEL_STANDARD);

        $this->RegisterPropertyInteger('update_interval', 5);

        $this->RegisterTimer('UpdateData', 0, 'VWCarNet_UpdateData(' . $this->InstanceID . ');');

        $this->CreateVarProfile('VWCarNet.Mileage', VARIABLETYPE_INTEGER, ' km', 0, 0, 0, 0, 'Distance');
        $this->CreateVarProfile('VWCarNet.Days', VARIABLETYPE_INTEGER, ' ' . $this->Translate('days'), 0, 0, 0, 0, '');
        $this->CreateVarProfile('VWCarNet.Mins', VARIABLETYPE_INTEGER, ' ' . $this->Translate('mins'), 0, 0, 0, 0, '');

        $this->CreateVarProfile('VWCarNet.Location', VARIABLETYPE_FLOAT, ' °', 0, 0, 0, 5, '');
        $this->CreateVarProfile('VWCarNet.Temperature', VARIABLETYPE_FLOAT, ' °C', 0, 0, 0, 0, '');
        $this->CreateVarProfile('VWCarNet.BatteryLevel', VARIABLETYPE_FLOAT, ' %', 0, 0, 0, 0, '');

        $associations = [
                ['Wert' => VW_PARKINGLIGHT_UNKNOWN, 'Name' => $this->Translate('unknown state'), 'Farbe' => -1],
                ['Wert' => VW_PARKINGLIGHT_OFF, 'Name' => $this->Translate('off'), 'Farbe' => -1],
                ['Wert' => VW_PARKINGLIGHT_LEFT, 'Name' => $this->Translate('left side on'), 'Farbe' => -1],
                ['Wert' => VW_PARKINGLIGHT_RIGHT, 'Name' => $this->Translate('right side on'), 'Farbe' => -1],
                ['Wert' => VW_PARKINGLIGHT_BOTH, 'Name' => $this->Translate('both side on'), 'Farbe' => -1],
            ];
        $this->CreateVarProfile('VWCarNet.ParkingLight', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [
                $associations[] = ['Wert' => VW_PARKINGBREAK_UNKNOWN, 'Name' => $this->Translate('unknown state'), 'Farbe' => -1],
                $associations[] = ['Wert' => VW_PARKINGBREAK_RELEASED, 'Name' => $this->Translate('released'), 'Farbe' => -1],
                $associations[] = ['Wert' => VW_PARKINGBREAK_TIGHTENED, 'Name' => $this->Translate('tightened'), 'Farbe' => -1],
            ];
        $this->CreateVarProfile('VWCarNet.ParkingBreak', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [
                ['Wert' => VW_SERVICEMESSAGE_UNKNOWN, 'Name' => $this->Translate('unknown state'), 'Farbe' => -1],
                ['Wert' => VW_SERVICEMESSAGE_NONE, 'Name' => $this->Translate('no message'), 'Farbe' => -1],
                ['Wert' => VW_SERVICEMESSAGE_WARNING, 'Name' => $this->Translate('warning'), 'Farbe' => -1],
            ];
        $this->CreateVarProfile('VWCarNet.ServiceMessage', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [
                ['Wert' => VW_DOORSTATE_UNKNOWN, 'Name' => $this->Translate('unknown state'), 'Farbe' => -1],
                ['Wert' => VW_DOORSTATE_UNSUPPORTED, 'Name' => $this->Translate('unsupported'), 'Farbe' => -1],
                ['Wert' => VW_DOORSTATE_OPENED, 'Name' => $this->Translate('opened'), 'Farbe' => -1],
                ['Wert' => VW_DOORSTATE_CLOSED, 'Name' => $this->Translate('closed'), 'Farbe' => -1],
                ['Wert' => VW_DOORSTATE_LOCKED, 'Name' => $this->Translate('locked'), 'Farbe' => -1],
            ];
        $this->CreateVarProfile('VWCarNet.DoorState', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [
                ['Wert' => VW_WINDOWSTATE_UNKNOWN, 'Name' => $this->Translate('unknown state'), 'Farbe' => -1],
                ['Wert' => VW_WINDOWSTATE_UNSUPPORTED, 'Name' => $this->Translate('unsupported'), 'Farbe' => -1],
                ['Wert' => VW_WINDOWSTATE_OPENED, 'Name' => $this->Translate('opened'), 'Farbe' => -1],
                ['Wert' => VW_WINDOWSTATE_CLOSED, 'Name' => $this->Translate('closed'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('VWCarNet.WindowState', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [
                ['Wert' => VW_CHARGINGSTATE_UNKNOWN, 'Name' => $this->Translate('unknown state'), 'Farbe' => -1],
                ['Wert' => VW_CHARGINGSTATE_OFF, 'Name' => $this->Translate('off'), 'Farbe' => -1],
                ['Wert' => VW_CHARGINGSTATE_CHARGING, 'Name' => $this->Translate('charging'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('VWCarNet.ChargingState', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $model = $this->ReadPropertyInteger('model');
        $with_electric = $model != VW_MODEL_STANDARD;

        $vpos = 1;
        $this->MaintainVariable('Mileage', $this->Translate('Mileage'), VARIABLETYPE_INTEGER, 'VWCarNet.Mileage', $vpos++, true);
        $this->MaintainVariable('Range', $this->Translate('Range'), VARIABLETYPE_INTEGER, 'VWCarNet.Mileage', $vpos++, true);

        $this->MaintainVariable('BatteryLevel', $this->Translate('Battery level'), VARIABLETYPE_FLOAT, 'VWCarNet.BatteryLevel', $vpos++, $with_electric);
        $this->MaintainVariable('ChargingState', $this->Translate('Charging state'), VARIABLETYPE_INTEGER, 'VWCarNet.ChargingState', $vpos++, $with_electric);
        $this->MaintainVariable('StateOfCharge', $this->Translate('State of charge'), VARIABLETYPE_FLOAT, 'VWCarNet.BatteryLevel', $vpos++, $with_electric);
        $this->MaintainVariable('RemainingChargingTime', $this->Translate('Remaining charging time'), VARIABLETYPE_INTEGER, 'VWCarNet.Mins', $vpos++, $with_electric);

        $vpos = 50;
        $this->MaintainVariable('DriverDoor', $this->Translate('Driver door'), VARIABLETYPE_INTEGER, 'VWCarNet.DoorState', $vpos++, true);
        $this->MaintainVariable('FrontPassengerDoor', $this->Translate('Front passenger door'), VARIABLETYPE_INTEGER, 'VWCarNet.DoorState', $vpos++, true);
        $this->MaintainVariable('RearLeftDoor', $this->Translate('Rear left door'), VARIABLETYPE_INTEGER, 'VWCarNet.DoorState', $vpos++, true);
        $this->MaintainVariable('RearRightDoor', $this->Translate('Rear right door'), VARIABLETYPE_INTEGER, 'VWCarNet.DoorState', $vpos++, true);

        $vpos = 60;
        $this->MaintainVariable('SunRoof', $this->Translate('Sunroof'), VARIABLETYPE_INTEGER, 'VWCarNet.WindowState', $vpos++, true);

        $vpos = 70;
        $this->MaintainVariable('ParkingLight', $this->Translate('Parking light'), VARIABLETYPE_INTEGER, 'VWCarNet.ParkingLight', $vpos++, true);
        $this->MaintainVariable('ParkingBreak', $this->Translate('Parking break'), VARIABLETYPE_INTEGER, 'VWCarNet.ParkingBreak', $vpos++, true);

        $this->MaintainVariable('TemperatureOutside', $this->Translate('Temperature outside'), VARIABLETYPE_FLOAT, 'VWCarNet.Temperature', $vpos++, true);

        $vpos = 80;
        $this->MaintainVariable('LastLongitude', $this->Translate('Last position (longitude)'), VARIABLETYPE_FLOAT, 'VWCarNet.Location', $vpos++, true);
        $this->MaintainVariable('LastLatitude', $this->Translate('Last position (latitude)'), VARIABLETYPE_FLOAT, 'VWCarNet.Location', $vpos++, true);
        $this->MaintainVariable('PositionTimestamp', $this->Translate('Timestamp of last position'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('ParkingTimestamp', $this->Translate('Timestamp of parking'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $vpos = 90;
        $this->MaintainVariable('ServiceInKm', $this->Translate('Service in'), VARIABLETYPE_INTEGER, 'VWCarNet.Mileage', $vpos++, true);
        $this->MaintainVariable('ServiceInDays', $this->Translate('Service in'), VARIABLETYPE_INTEGER, 'VWCarNet.Days', $vpos++, true);
        $this->MaintainVariable('ServiceMessage', $this->Translate('Service message'), VARIABLETYPE_INTEGER, 'VWCarNet.ServiceMessage', $vpos++, true);

        $vpos = 100;
        /*
            maxChargeCurrent
            chargingMode
            chargingReason
            externalPowerSupplyState
            energyFlow
        */

        $vpos = 120;
        /*
            climateHeatingStatus
            climateHeatingWindowFrontStatus
            climateHeatingWindowRearStatus
        */

        $vpos = 200;
        $this->MaintainVariable('LastUpdate', $this->Translate('Last update'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $username = $this->ReadPropertyString('username');
        $password = $this->ReadPropertyString('password');

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        if ($username != '' && $password != '') {
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->SetStatus(IS_INVALIDCONFIG);
        }

        $this->SetUpdateInterval();
    }

    public function GetConfigurationForm()
    {
        $model_opts = [];
        $model_opts[] = ['label' => 'Standard', 'value' => VW_MODEL_STANDARD];
        $model_opts[] = ['label' => 'Hybrid', 'value' => VW_MODEL_HYBRID];
        $model_opts[] = ['label' => 'Electric', 'value' => VW_MODEL_ELECTRIC];

        $formElements = [];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'module_disable', 'caption' => 'Instance is disabled'];
        $formElements[] = ['type' => 'Label', 'label' => 'Volkswagen Car-Net Account'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'username', 'caption' => 'User-ID (email)'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'password', 'caption' => 'Password'];
        $formElements[] = ['type' => 'Label', 'label' => ''];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'vin', 'caption' => 'VIN'];
        $formElements[] = ['type' => 'Label', 'label' => ''];
        $formElements[] = ['type' => 'Select', 'name' => 'model', 'caption' => 'Model', 'options' => $model_opts];
        $formElements[] = ['type' => 'Label', 'label' => 'Update data every X minutes'];
        $formElements[] = ['type' => 'NumberSpinner', 'name' => 'update_interval', 'caption' => 'Minutes'];

        $formActions = [];
        $formActions[] = ['type' => 'Button', 'caption' => 'Test access', 'onClick' => 'VWCarNet_TestAccess($id);'];
        $formActions[] = ['type' => 'Button', 'caption' => 'Update data', 'onClick' => 'VWCarNet_UpdateData($id);'];
        $formActions[] = ['type' => 'Label', 'label' => '____________________________________________________________________________________________________'];
        $formActions[] = [
                            'type'    => 'Button',
                            'caption' => 'Module description',
                            'onClick' => 'echo "https://github.com/demel42/IPSymconVWCarNet/blob/master/README.md";'
                        ];

        $formStatus = [];
        $formStatus[] = ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => IS_DELETING, 'icon' => 'inactive', 'caption' => 'Instance is deleted'];
        $formStatus[] = ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => IS_NOTCREATED, 'icon' => 'inactive', 'caption' => 'Instance is not created'];

        $formStatus[] = ['code' => IS_INVALIDCONFIG, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid configuration)'];
        $formStatus[] = ['code' => IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];

        return json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
    }

    public function SetUpdateInterval(int $Minutes = null)
    {
        if (!($Minutes > 0)) {
            $Minutes = $this->ReadPropertyInteger('update_interval');
        }
        $interval = $Minutes * 60 * 1000;
        $this->SendDebug(__FUNCTION__, 'minutes=' . $Minutes, 0);
        $this->SetTimerInterval('UpdateData', $interval);
    }

    public function UpdateData()
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $model = $this->ReadPropertyInteger('model');

        $this->getState();
        $this->getPosition();

        $this->getClimater();

        if ($model != VW_MODEL_STANDARD) {
            $this->getCharger();
        }

        $this->SetValue('LastUpdate', time());
    }

    public function TestAccess()
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            echo $this->translate('Instance is inactive') . PHP_EOL;
            return;
        }

        $vin = $this->ReadPropertyString('vin');

        $txt = '';

        $cdata = '';
        $msg = '';
        $r = $this->do_ApiCall('/usermanagement/users/v1/VW/DE/vehicles', $cdata, $msg);
        if ($r == false) {
            $txt .= $this->Translate('invalid account-data') . PHP_EOL;
            $txt .= PHP_EOL;
            if ($msg != '') {
                $txt .= $this->Translate('message') . ': ' . $msg . PHP_EOL;
            }
        } else {
            $txt = $this->Translate('valid account-data') . PHP_EOL;

            $jdata = json_decode($cdata, true);
            $vehicles = $jdata['userVehicles']['vehicle'];
            $fnd = false;
            foreach ($vehicles as $vehicle) {
                if ($vin == $vehicle) {
                    $fnd = true;
                }
            }

            $txt .= PHP_EOL;
            if ($vin == '') {
                $txt .= $this->Translate('No VIN configured') . PHP_EOL;
            } else {
                if ($fnd) {
                    $txt .= $this->Translate('The given VIN is registered in this account') . PHP_EOL;
                } else {
                    $txt .= $this->Translate('The given VIN is not registered in this account') . PHP_EOL;
                }
            }

            $txt .= PHP_EOL;
            $txt .= $this->Translate('List of VIN') . PHP_EOL;
            foreach ($vehicles as $vehicle) {
                $txt .= '  ' . $vehicle . PHP_EOL;
            }
        }

        echo $txt;
    }

    private function getToken(&$msg)
    {
        $username = $this->ReadPropertyString('username');
        $password = $this->ReadPropertyString('password');

        $dtoken = $this->GetBuffer('Token');
        $jtoken = json_decode($dtoken, true);
        $token = isset($jtoken['token']) ? $jtoken['token'] : '';
        $expiration = isset($jtoken['expiration']) ? $jtoken['expiration'] : 0;

        if ($expiration < time()) {
            $header = [
                    'Accept: application/json',
                    'X-App-Name: eRemote',
                    'X-App-Version: 1.0.0',
                    'User-Agent: okhttp/2.3.0'
                ];
            $postdata = [
                    'grant_type' => 'password',
                    'username'   => $username,
                    'password'   => $password
                ];

            $cdata = '';
            $msg = '';
            $statuscode = $this->do_HttpRequest('/core/auth/v1/VW/DE/token', '', $header, $postdata, 'POST', $cdata, $msg);
            if ($statuscode == 0 && $cdata == '') {
                $statuscode = IS_INVALIDDATA;
            }
            $this->SendDebug(__FUNCTION__, 'statuscode=' . $statuscode . ', cdata=' . print_r($cdata, true) . ', msg=' . $msg, 0);
            if ($statuscode != 0) {
                $this->SetStatus($statuscode);
                return '';
            }

            $jdata = json_decode($cdata, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

            $token = $jdata['access_token'];
            $expires_in = $jdata['expires_in'];

            $jtoken = [
                    'token'      => $token,
                    'expiration' => time() + $expires_in
                ];
            $this->SetBuffer('Token', json_encode($jtoken));
        }

        return $jtoken;
    }

    private function do_ApiCall($func, &$data, &$msg)
    {
        $vin = $this->ReadPropertyString('vin');

        $jtoken = $this->getToken($msg);
        if ($jtoken == '') {
            return false;
        }
        $token = $jtoken['token'];

        $header = [
                    'Accept: application/json',
                    'X-App-Name: eRemote',
                    'X-App-Version: 1.0.0',
                    'User-Agent: okhttp/2.3.0',
                    'Authorization: AudiAuth 1 ' . $token,
                ];

        $msg = '';
        $statuscode = $this->do_HttpRequest($func, '', $header, '', 'GET', $data, $msg);
        $this->SendDebug(__FUNCTION__, 'statuscode=' . $statuscode . ', data=' . print_r($data, true), 0);
        if ($statuscode != 0) {
            $this->SetStatus($statuscode);
            return false;
        }

        $this->SetStatus(IS_ACTIVE);
        return $statuscode ? false : true;
    }

    private function do_HttpRequest($func, $params, $header, $postdata, $mode, &$data, &$msg)
    {
        $url = 'https://msg.volkswagen.de/fs-car' . $func;

        if ($params != '') {
            $n = 0;
            foreach ($params as $param => $value) {
                $url .= ($n++ ? '&' : '?') . $param . '=' . rawurlencode($value);
            }
        }

        $this->SendDebug(__FUNCTION__, 'http-' . $mode . ': url=' . $url, 0);
        $this->SendDebug(__FUNCTION__, '    header=' . print_r($header, true), 0);
        if ($postdata != '') {
            $postdata = http_build_query($postdata);
            $this->SendDebug(__FUNCTION__, '    postdata=' . $postdata, 0);
        }

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        switch ($mode) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
                break;
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $cdata = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, ' => cdata=' . $cdata, 0);

        $statuscode = 0;
        $err = '';
        $msg = '';
        $data = '';

        if ($cdata != '') {
            $jdata = json_decode($cdata, true);
            if (isset($jdata['error']['description'])) {
                $msg = $jdata['error']['description'];
            }
        }

        if ($cerrno) {
            $statuscode = IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode == 200) {
            $data = $cdata;
        } elseif ($httpcode == 302) {
            $data = $redirect_url;
        } elseif ($httpcode == 401) {
            $statuscode = IS_UNAUTHORIZED;
            $err = 'got http-code ' . $httpcode . ' (unauthorized)';
        } elseif ($httpcode >= 500 && $httpcode <= 599) {
            $statuscode = IS_SERVERERROR;
            $err = 'got http-code ' . $httpcode . ' (server error)';
        } else {
            $statuscode = IS_HTTPERROR;
            $err = 'got http-code ' . $httpcode;
        }

        if ($statuscode) {
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err . ', msg=' . $msg, 0);
        }

        return $statuscode;
    }

    private function getState()
    {
        $vin = $this->ReadPropertyString('vin');
        $func = '/bs/vsr/v1/VW/DE/vehicles/' . $vin . '/status';

        $cdata = '';
        $msg = '';
        if (!defined('VW_TEST_STATUS')) {
            $r = $this->do_ApiCall($func, $cdata, $msg);
        } else {
            $cdata = VW_TEST_STATUS;
            $r = $cdata != '';
        }
        if ($r) {
            $jdata = json_decode($cdata, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

            $model = $this->ReadPropertyInteger('model');
            $with_electric = $model != VW_MODEL_STANDARD;

            $usedFields = [];

            $mileage = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.1.field.0.value', 0);
            $usedFields[1][0] = true;
            $this->SendDebug(__FUNCTION__, utf8_decode('mileage=' . $mileage . ' km'), 0);
            $this->SetValue('Mileage', $mileage);

            $serviceInKm = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.2.field.2.value', 0);
            $usedFields[2][2] = true;
            $serviceInKm *= -1;
            $this->SendDebug(__FUNCTION__, utf8_decode('serviceInKm=' . $serviceInKm . ' km'), 0);
            $this->SetValue('ServiceInKm', $serviceInKm);

            $serviceInDays = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.2.field.3.value', 0);
            $usedFields[2][3] = true;
            $serviceInDays *= -1;
            $this->SendDebug(__FUNCTION__, utf8_decode('serviceInDays=' . $serviceInDays . ' days'), 0);
            $this->SetValue('ServiceInDays', $serviceInDays);

            $_serviceMessage = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.2.field.4.value', 0);
            $usedFields[2][4] = true;
            $serviceMessage = $this->decode_serviceMessage($_serviceMessage);
            $this->SendDebug(__FUNCTION__, utf8_decode('serviceMessage=' . $_serviceMessage . ' => ' . $serviceMessage), 0);
            $this->SetValue('ServiceMessage', $serviceMessage);

            $_parkingLight = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.3.field.0.value', 0);
            $usedFields[3][0] = true;
            $parkingLight = $this->decode_parkingLight($_parkingLight);
            $this->SendDebug(__FUNCTION__, utf8_decode('parkingLight=' . $_parkingLight . ' => ' . $parkingLight), 0);
            $this->SetValue('ParkingLight', $parkingLight);

            $_parkingBrake = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.5.field.0.value', 0);
            $usedFields[5][0] = true;
            $parkingBrake = $this->decode_parkingBrake($_parkingBrake);
            $this->SendDebug(__FUNCTION__, utf8_decode('parkingBrake=' . $_parkingBrake . ' => ' . $parkingBrake), 0);
            $this->SetValue('ParkingBreak', $parkingBrake);

            $_temperatureOutside = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.4.field.0.value', '');
            $usedFields[4][0] = true;
            if ($_temperatureOutside != '') {
                $temperatureOutside = intval($_temperatureOutside) / 10.0 - 273;
            } else {
                $temperatureOutside = 0;
            }
            $this->SendDebug(__FUNCTION__, utf8_decode('temperatureOutside=' . $_temperatureOutside . ' => ' . $temperatureOutside . ' °C'), 0);
            $this->SetValue('TemperatureOutside', $temperatureOutside);

            if ($with_electric) {
                $batteryLevel = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.5.field.1.value', 0);
                $usedFields[5][1] = true;
                $this->SendDebug(__FUNCTION__, utf8_decode('batteryLevel=' . $batteryLevel . ' %'), 0);
                $this->SetValue('BatteryLevel', $batteryLevel);
            }

            $range = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.5.field.4.value', 0);
            $usedFields[5][4] = true;
            $this->SendDebug(__FUNCTION__, utf8_decode('range=' . $range . ' km'), 0);
            $this->SetValue('Range', $range);

            $driverDoorLockState = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.6.field.0.value', 0);
            $usedFields[6][0] = true;
            $driverDoorCloseState = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.6.field.1.value', 0);
            $usedFields[6][1] = true;
            $usedFields[6][2] = true; // ignorieren
            $driverDoor = $this->decode_doorState($driverDoorLockState, $driverDoorCloseState);
            $this->SendDebug(__FUNCTION__, utf8_decode('driverDoor=' . $driverDoorLockState . '/' . $driverDoorCloseState . ' => ' . $driverDoor), 0);
            $this->SetValue('DriverDoor', $driverDoor);

            $rearLeftDoorLockState = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.6.field.3.value', 0);
            $usedFields[6][3] = true;
            $rearLeftDoorCloseState = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.6.field.4.value', 0);
            $usedFields[6][4] = true;
            $usedFields[6][5] = true; // ignorieren
            $rearLeftDoor = $this->decode_doorState($rearLeftDoorLockState, $rearLeftDoorCloseState);
            $this->SendDebug(__FUNCTION__, utf8_decode('rearLeftDoor=' . $rearLeftDoorLockState . '/' . $rearLeftDoorCloseState . ' => ' . $rearLeftDoor), 0);
            $this->SetValue('RearLeftDoor', $rearLeftDoor);

            $frontPassengerDoorLockState = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.6.field.6.value', 0);
            $usedFields[6][6] = true;
            $frontPassengerDoorCloseState = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.6.field.7.value', 0);
            $usedFields[6][7] = true;
            $usedFields[6][8] = true; // ignorieren
            $frontPassengerDoor = $this->decode_doorState($frontPassengerDoorLockState, $frontPassengerDoorCloseState);
            $this->SendDebug(__FUNCTION__, utf8_decode('frontPassengerDoor=' . $frontPassengerDoorLockState . '/' . $frontPassengerDoorCloseState . ' => ' . $frontPassengerDoor), 0);
            $this->SetValue('FrontPassengerDoor', $frontPassengerDoor);

            $rearRightDoorLockState = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.6.field.9.value', 0);
            $usedFields[6][9] = true;
            $rearRightDoorCloseState = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.6.field.10.value', 0);
            $usedFields[6][10] = true;
            $usedFields[6][11] = true; // ignorieren
            $rearRightDoor = $this->decode_doorState($rearRightDoorLockState, $rearRightDoorCloseState);
            $this->SendDebug(__FUNCTION__, utf8_decode('rearRightDoor=' . $rearRightDoorLockState . '/' . $rearRightDoorCloseState . ' => ' . $rearRightDoor), 0);
            $this->SetValue('RearRightDoor', $rearRightDoor);

            $_sunroofState = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.7.field.5.value', 0);
            $usedFields[7][5] = true;
            $sunroofState = $this->decode_windowState($_sunroofState);
            $this->SendDebug(__FUNCTION__, utf8_decode('sunroofState=' . $_sunroofState . ' => ' . $sunroofState), 0);
            $this->SetValue('SunRoof', $sunroofState);

            $this->SendDebug(__FUNCTION__, utf8_decode('unused fields'), 0);
            for ($d = 0; $d < 10; $d++) {
                for ($f = 0; $f < 99; $f++) {
                    if (isset($usedFields[$d][$f])) {
                        continue;
                    }
                    $s = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.' . $d . '.field.' . $f . '.value', '');
                    if ($s != '') {
                        $r = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.' . $d . '.field.' . $f . '.textId', '');
                        $p = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.' . $d . '.field.' . $f . '.unit', '');
                        $this->SendDebug(__FUNCTION__, utf8_decode('   .data.' . $d . '.field.' . $f . '=' . $s . ' ' . $r . ' ' . $p), 0);
                    }
                }
            }
        }
    }

    private function getPosition()
    {
        $vin = $this->ReadPropertyString('vin');
        $func = '/bs/cf/v1/VW/DE/vehicles/' . $vin . '/position';

        $cdata = '';
        $msg = '';
        if (!defined('VW_TEST_POSITION')) {
            $r = $this->do_ApiCall($func, $cdata, $msg);
        } else {
            $cdata = VW_TEST_POSITION;
            $r = $cdata != '';
        }
        if ($r) {
            $jdata = json_decode($cdata, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

            $_lat = $this->GetArrayElem($jdata, 'findCarResponse.Position.carCoordinate.latitude', '');
            if ($_lat != '') {
                $lat = substr($_lat, 0, strlen($_lat) - 6) . '.' . substr($_lat, strlen($_lat) - 6);
            } else {
                $lat = 0;
            }
            $_lon = $this->GetArrayElem($jdata, 'findCarResponse.Position.carCoordinate.longitude', '');
            if ($_lon != '') {
                $lon = substr($_lon, 0, strlen($_lon) - 6) . '.' . substr($_lon, strlen($_lon) - 6);
            } else {
                $lon = 0;
            }
            $this->SendDebug(__FUNCTION__, utf8_decode('latitude=' . $_lat . ' => ' . $lat . ', longitude=' . $_lon . ' => ' . $lon), 0);
            $this->SetValue('LastLatitude', $lat);
            $this->SetValue('LastLongitude', $lon);

            $_positionTimestamp = $this->GetArrayElem($jdata, 'findCarResponse.Position.timestampCarSent', '');
            $positionTimestamp = strtotime($_positionTimestamp);
            $this->SendDebug(__FUNCTION__, utf8_decode('positionTimestamp=' . $_positionTimestamp . ' => ' . date('d.m.Y H:i', $positionTimestamp)), 0);
            $this->SetValue('ParkingTimestamp', $positionTimestamp);

            $_parkingTimestamp = $this->GetArrayElem($jdata, 'findCarResponse.parkingTimeUTC', '');
            $parkingTimestamp = strtotime($_parkingTimestamp);
            $this->SendDebug(__FUNCTION__, utf8_decode('parkingTimestamp=' . $_parkingTimestamp . ' => ' . date('d.m.Y H:i', $parkingTimestamp)), 0);
            $this->SetValue('PositionTimestamp', $parkingTimestamp);
        }
    }

    private function getClimater()
    {
        $vin = $this->ReadPropertyString('vin');
        $func = '/bs/climatisation/v1/VW/DE/vehicles/' . $vin . '/climater';

        $cdata = '';
        $msg = '';
        if (!defined('VW_TEST_CLIMATER')) {
            $r = $this->do_ApiCall($func, $cdata, $msg);
        } else {
            $cdata = VW_TEST_CLIMATER;
            $r = $cdata != '';
        }
        if ($r) {
            $jdata = json_decode($cdata, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

            $_carTemp = $this->GetArrayElem($jdata, 'climater.status.temperatureStatusData.outdoorTemperature.content', '');
            if ($_carTemp != '') {
                $carTemp = (floatval($_carTemp) / 10.0) - 273;
            } else {
                $carTemp = 0;
            }
            $this->SendDebug(__FUNCTION__, utf8_decode('carTemp=' . $_carTemp . ' => ' . $carTemp), 0);

            $climateHeatingStatus = $this->GetArrayElem($jdata, 'climater.status.climatisationStatusData.climatisationState.content', '');
            $this->SendDebug(__FUNCTION__, utf8_decode('climateHeatingStatus=' . $climateHeatingStatus), 0);

            $climateHeatingWindowFrontStatus = $this->GetArrayElem($jdata, 'climater.status.windowHeatingStatusData.windowHeatingStateFront.content', '');
            $this->SendDebug(__FUNCTION__, utf8_decode('climateHeatingWindowFrontStatus=' . $climateHeatingWindowFrontStatus), 0);

            $climateHeatingWindowRearStatus = $this->GetArrayElem($jdata, 'climater.status.windowHeatingStatusData.windowHeatingStateRear.content', '');
            $this->SendDebug(__FUNCTION__, utf8_decode('climateHeatingWindowRearStatus=' . $climateHeatingWindowRearStatus), 0);
        }
    }

    private function getCharger()
    {
        $vin = $this->ReadPropertyString('vin');
        $func = '/bs/batterycharge/v1/VW/DE/vehicles/' . $vin . '/charger';

        $cdata = '';
        $msg = '';
        if (!defined('VW_TEST_CHARGER')) {
            $r = $this->do_ApiCall($func, $cdata, $msg);
        } else {
            $cdata = VW_TEST_CHARGER;
            $r = $cdata != '';
        }
        if ($r) {
            $jdata = json_decode($cdata, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

            $maxChargeCurrent = $this->GetArrayElem($jdata, 'charger.settings.maxChargeCurrent.content', '');
            $this->SendDebug(__FUNCTION__, utf8_decode('maxChargeCurrent=' . $maxChargeCurrent), 0);

            $chargingMode = $this->GetArrayElem($jdata, 'charger.status.chargingStatusData.chargingMode.content', '');
            $this->SendDebug(__FUNCTION__, utf8_decode('chargingMode=' . $chargingMode), 0);
            $chargingReason = $this->GetArrayElem($jdata, 'charger.status.chargingStatusData.chargingReason.content', '');
            $this->SendDebug(__FUNCTION__, utf8_decode('chargingReason=' . $chargingReason), 0);
            $externalPowerSupplyState = $this->GetArrayElem($jdata, 'charger.status.chargingStatusData.externalPowerSupplyState.content', '');
            $this->SendDebug(__FUNCTION__, utf8_decode('externalPowerSupplyState=' . $externalPowerSupplyState), 0);
            $energyFlow = $this->GetArrayElem($jdata, 'charger.status.chargingStatusData.energyFlow.content', '');
            $this->SendDebug(__FUNCTION__, utf8_decode('energyFlow=' . $energyFlow), 0);

            $_chargingState = $this->GetArrayElem($jdata, 'charger.status.chargingStatusData.chargingState.content', '');
            $chargingState = $this->decode_chargingState($_chargingState);
            $this->SendDebug(__FUNCTION__, utf8_decode('chargingState=' . $_chargingState . ' => ' . $chargingState), 0);
            $this->SetValue('ChargingState', $chargingState);

            $primaryEngineRange = $this->GetArrayElem($jdata, 'charger.status.cruisingRangeStatusData.primaryEngineRange.content', '');
            $this->SendDebug(__FUNCTION__, utf8_decode('primaryEngineRange=' . $primaryEngineRange), 0);

            $stateOfCharge = $this->GetArrayElem($jdata, 'charger.status.batteryStatusData.stateOfCharge.content', '');
            $this->SendDebug(__FUNCTION__, utf8_decode('stateOfCharge=' . $stateOfCharge), 0);
            $this->SetValue('StateOfCharge', $stateOfCharge);

            $_remainingChargingTime = $this->GetArrayElem($jdata, 'charger.status.batteryStatusData.remainingChargingTime.content', 0);
            $remainingChargingTime = $chargingState == VW_CHARGINGSTATE_CHARGING ? $_remainingChargingTime : 0;
            $this->SendDebug(__FUNCTION__, utf8_decode('remainingChargingTime=' . $_remainingChargingTime . ' => ' . $remainingChargingTime), 0);
            $this->SetValue('RemainingChargingTime', $remainingChargingTime);

            $remainingChargingTimeTargetSOC = $this->GetArrayElem($jdata, 'charger.status.batteryStatusData.remainingChargingTimeTargetSOC.content', '');
            $this->SendDebug(__FUNCTION__, utf8_decode('remainingChargingTimeTargetSOC=' . $remainingChargingTimeTargetSOC), 0);

            $ledColor = $this->GetArrayElem($jdata, 'charger.status.ledStatusData.ledColor.content', '');
            $this->SendDebug(__FUNCTION__, utf8_decode('ledColor=' . $ledColor), 0);
            $ledState = $this->GetArrayElem($jdata, 'charger.status.ledStatusData.ledState.content', '');
            $this->SendDebug(__FUNCTION__, utf8_decode('ledState=' . $ledState), 0);

            $plugState = $this->GetArrayElem($jdata, 'charger.status.plugStatusData.plugState.content', '');
            $this->SendDebug(__FUNCTION__, utf8_decode('plugState=' . $plugState), 0);
            $lockState = $this->GetArrayElem($jdata, 'charger.status.plugStatusData.lockState.content', '');
            $this->SendDebug(__FUNCTION__, utf8_decode('lockState=' . $lockState), 0);
        }
    }

    private function decode_serviceMessage($serviceMessage)
    {
        switch ($serviceMessage) {
            case 0:
                $retval = VW_SERVICEMESSAGE_NONE;
                break;
            case 1:
                $retval = VW_SERVICEMESSAGE_WARNING;
                break;
            default:
                $retval = VW_SERVICEMESSAGE_UNKNOWN;
                $e = 'unknown value ' . $serviceMessage;
                $this->SendDebug(__FUNCTION__, $e, 0);
                $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_WARNING);
                break;
        }
        return $retval;
    }

    private function decode_parkingBrake($parkingBrake)
    {
        switch ($parkingBrake) {
            case 0:
                $retval = VW_PARKINGBREAK_RELEASED;
                break;
            case 1:
                $retval = VW_PARKINGBREAK_TIGHTENED;
                break;
            default:
                $retval = VW_PARKINGBREAK_UNKNOWN;
                $e = 'unknown value ' . $parkingBrake;
                $this->SendDebug(__FUNCTION__, $e, 0);
                $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_WARNING);
                break;
        }
        return $retval;
    }

    private function decode_parkingLight($parkingLight)
    {
        switch ($parkingLight) {
            case 2:
                $retval = VW_PARKINGLIGHT_OFF;
                break;
            case 3:
                $retval = VW_PARKINGLIGHT_LEFT;
                break;
            case 4:
                $retval = VW_PARKINGLIGHT_RIGHT;
                break;
            case 5:
                $retval = VW_PARKINGLIGHT_BOTH;
                break;
            default:
                $retval = VW_PARKINGLIGHT_UNKNOWN;
                $e = 'unknown value ' . $parkingLight;
                $this->SendDebug(__FUNCTION__, $e, 0);
                $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_WARNING);
                break;
        }
        return $retval;
    }

    private function decode_doorState($doorLockState, $doorCloseState)
    {
        $retval = VW_DOORSTATE_UNKNOWN;

        if ($doorLockState == 0 || $doorCloseState == 0) {
            $retval = VW_DOORSTATE_UNSUPPORTED;
        } elseif ($doorLockState == 2) {
            $retval = VW_DOORSTATE_LOCKED;
        } elseif ($doorCloseState == 3) {
            $retval = VW_DOORSTATE_CLOSED;
        } elseif ($doorCloseState == 2) {
            $retval = VW_DOORSTATE_OPENED;
        }

        if ($retval == VW_DOORSTATE_UNKNOWN) {
            $e = 'unknown value ' . $doorLockState . '/' . $doorCloseState;
            $this->SendDebug(__FUNCTION__, $e, 0);
            $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_WARNING);
        }
        return $retval;
    }

    private function decode_windowState($windowState)
    {
        switch ($windowState) {
            case 0:
                $retval = VW_WINDOWSTATE_UNSUPPORTED;
                break;
            case 2:
                $retval = VW_WINDOWSTATE_OPENED;
                break;
            case 3:
                $retval = VW_WINDOWSTATE_CLOSED;
                break;
            default:
                $retval = VW_WINDOWSTATE_UNKNOWN;
                $e = 'unknown value ' . $windowState;
                $this->SendDebug(__FUNCTION__, $e, 0);
                $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_WARNING);
                break;
        }
        return $retval;
    }

    private function decode_chargingState($chargingState)
    {
        switch ($chargingState) {
            case 'off':
                $retval = VW_CHARGINGSTATE_OFF;
                break;
            case 'charging':
                $retval = VW_CHARGINGSTATE_CHARGING;
                break;
            default:
                $retval = VW_CHARGINGSTATE_UNKNOWN;
                $e = 'unknown value ' . $chargingState;
                $this->SendDebug(__FUNCTION__, $e, 0);
                $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_WARNING);
                break;
        }
        return $retval;
    }
}
