<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

class VWCarNet extends IPSModule
{
    use VWCarNetCommon;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('username', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('vin', '');

        $this->RegisterPropertyInteger('update_interval', 5);

        $this->RegisterTimer('UpdateData', 0, 'VWCarNet_UpdateData(' . $this->InstanceID . ');');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $username = $this->ReadPropertyString('username');
        $password = $this->ReadPropertyString('password');

        if ($username != '' && $password != '') {
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->SetStatus(IS_INVALIDCONFIG);
        }

        $this->SetUpdateInterval();
    }

    public function GetConfigurationForm()
    {
        $formElements = [];
        $formElements[] = ['type' => 'Label', 'label' => 'Volkswagen Car-Net Account'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'username', 'caption' => 'User-ID (email)'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'password', 'caption' => 'Password'];
        $formElements[] = ['type' => 'Label', 'label' => ''];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'vin', 'caption' => 'VIN'];
        $formElements[] = ['type' => 'Label', 'label' => 'Update data every X minutes'];
        $formElements[] = ['type' => 'IntervalBox', 'name' => 'update_interval', 'caption' => 'Minutes'];

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
        $this->getStatus();
        $this->getPosition();
        $this->getClimater();
        $this->getCharger();
    }

    public function TestAccess()
    {
        $vin = $this->ReadPropertyString('vin');

        $txt = '';

        $cdata = '';
        $msg = '';
        $r = $this->do_ApiCall('/usermanagement/users/v1/VW/DE/vehicles', $cdata, $msg);
        if ($r == false) {
            $txt .= $this->translate('invalid account-data') . PHP_EOL;
            $txt .= PHP_EOL;
            if ($msg != '') {
                $txt .= $this->translate('message') . ': ' . $msg . PHP_EOL;
            }
        } else {
            $txt = $this->translate('valid account-data') . PHP_EOL;

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
                $txt .= $this->translate('No VIN configured') . PHP_EOL;
            } else {
                if ($fnd) {
                    $txt .= $this->translate('The given VIN is registered in this account') . PHP_EOL;
                } else {
                    $txt .= $this->translate('The given VIN is not registered in this account') . PHP_EOL;
                }
            }

            $txt .= PHP_EOL;
            $txt .= $this->translate('List of VIN') . PHP_EOL;
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
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
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

        if ($httpcode == 200) {
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

    private function getStatus()
    {
        $vin = $this->ReadPropertyString('vin');
        $func = '/bs/vsr/v1/VW/DE/vehicles/' . $vin . '/status';

        $cdata = '';
        $msg = '';
        $r = $this->do_ApiCall($func, $cdata, $msg);
        /*
        $r = true;
        $cdata = '{"StoredVehicleDataResponse":{"vin":"WVWZZZAAZHDxxxxxx","vehicleData":{"data": [{"id":"0x0101010001","field": [{"id":"0x0101010001","tsCarSentUtc":"2018-12-16T12:53:40Z","tsCarSent":"2000-01-01T00:00:00","tsCarCaptured":"2000-01-01T00:00:00","tsTssReceivedUtc":"2018-12-16T12:53:40Z","milCarCaptured":0,"milCarSent":0,"value":"echo"}]},{"id":"0x0101010002","field": [{"id":"0x0101010002","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"8096"}]},{"id":"0x0203FFFFFF","field": [{"id":"0x0203010001","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T12:28:29","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"-1700","unit":"km"},{"id":"0x0203010002","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T12:28:29","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"-17","unit":"d"},{"id":"0x0203010003","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T12:28:29","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"-22000","unit":"km"},{"id":"0x0203010004","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T12:28:29","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"-29","unit":"d"},{"id":"0x0203010005","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T12:28:29","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"1","textId":"interval.inspection.warn"},{"id":"0x0203010006","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T12:28:29","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"1","textId":"interval.inspection.warn"},{"id":"0x0203010007","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T12:28:29","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"257","unit":"km"}]},{"id":"0x030101FFFF","field": [{"id":"0x0301010001","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"2","textId":"status_parking_light_off"}]},{"id":"0x030102FFFF","field": [{"id":"0x0301020001","tsCarSentUtc":"2018-12-16T12:53:40Z","tsCarSent":"2000-01-01T00:00:00","tsCarCaptured":"2000-01-01T00:00:00","tsTssReceivedUtc":"2018-12-16T12:53:40Z","milCarCaptured":0,"milCarSent":0,"value":"2765","unit":"dK"}]},{"id":"0x030103FFFF","field": [{"id":"0x0301030001","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"0","textId":"parking_brake_inactive"},{"id":"0x0301030002","tsCarSentUtc":"2018-12-16T12:53:39Z","tsCarSent":"2000-01-01T00:00:00","tsCarCaptured":"2000-01-01T00:00:00","tsTssReceivedUtc":"2018-12-16T12:53:39Z","milCarCaptured":0,"milCarSent":0,"value":"30","unit":"%","textId":"soc_ok"},{"id":"0x0301030003","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"0","unit":"%","textId":"bem_ok"},{"id":"0x0301030004","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"0","unit":"km/h","textId":"speed_ok"},{"id":"0x0301030006","tsCarSentUtc":"2018-12-16T12:53:39Z","tsCarSent":"2000-01-01T00:00:00","tsCarCaptured":"2000-01-01T00:00:00","tsTssReceivedUtc":"2018-12-16T12:53:39Z","milCarCaptured":0,"milCarSent":0,"value":"24","unit":"km","textId":"range_ok"},{"id":"0x0301030007","tsCarSentUtc":"2018-12-16T12:53:39Z","tsCarSent":"2000-01-01T00:00:00","tsCarCaptured":"2000-01-01T00:00:00","tsTssReceivedUtc":"2018-12-16T12:53:39Z","milCarCaptured":0,"milCarSent":0,"value":"3","textId":"engine_type_electric"},{"id":"0x030103000A","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"100","unit":"%","textId":"fuel_level_ok"},{"id":"0x030103000B","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"0","textId":"fuel_level_measured"}]},{"id":"0x030104FFFF","field": [{"id":"0x0301040001","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"2","textId":"door_locked"},{"id":"0x0301040002","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"3","textId":"door_closed"},{"id":"0x0301040003","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"3","textId":"door_unsafe"},{"id":"0x0301040004","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"2","textId":"door_locked"},{"id":"0x0301040005","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"3","textId":"door_closed"},{"id":"0x0301040006","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"3","textId":"door_unsafe"},{"id":"0x0301040007","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"2","textId":"door_locked"},{"id":"0x0301040008","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"3","textId":"door_closed"},{"id":"0x0301040009","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"3","textId":"door_unsafe"},{"id":"0x030104000A","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"2","textId":"door_locked"},{"id":"0x030104000B","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"3","textId":"door_closed"},{"id":"0x030104000C","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"3","textId":"door_unsafe"},{"id":"0x030104000D","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"2","textId":"door_locked"},{"id":"0x030104000E","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"3","textId":"door_closed"},{"id":"0x030104000F","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"3","textId":"door_unsafe"},{"id":"0x0301040010","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"3","textId":"door_unlocked"},{"id":"0x0301040011","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"3","textId":"door_closed"},{"id":"0x0301040012","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"3","textId":"door_unsafe"}]},{"id":"0x030105FFFF","field": [{"id":"0x0301050001","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"0","textId":"window_unsupported"},{"id":"0x0301050003","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"0","textId":"window_unsupported"},{"id":"0x0301050005","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"0","textId":"window_unsupported"},{"id":"0x0301050007","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"0","textId":"window_unsupported"},{"id":"0x0301050009","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"0","textId":"window_unsupported"},{"id":"0x030105000B","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"3","textId":"window_closed"},{"id":"0x030105000C","tsCarSentUtc":"2018-12-16T12:28:29Z","tsCarSent":"2018-12-16T13:28:28","tsCarCaptured":"2018-12-16T13:28:28","tsTssReceivedUtc":"2018-12-16T12:28:28Z","milCarCaptured":8096,"milCarSent":8096,"value":"0","unit":"%"}]}]}}}';
        */

        if ($r) {
            $jdata = json_decode($cdata, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

            $mileage = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.1.field.0.value', 0);
            $this->SendDebug(__FUNCTION__, 'mileage=' . $mileage, 0);

            $serviceInKm = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.2.field.2.value', 0);
            $serviceInKm *= -1;
            $this->SendDebug(__FUNCTION__, 'serviceInKm=' . $serviceInKm, 0);

            $serviceInDays = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.2.field.3.value', 0);
            $serviceInDays *= -1;
            $this->SendDebug(__FUNCTION__, 'serviceInDays=' . $serviceInDays, 0);

            $_parkingLight = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.3.field.0.value', 0);
            switch ($_parkingLight) {
                case 2:
                    $parkingLight = $this->translate('left') . '=' . $this->translate('off') . ', ' . $this->translate('right') . '=' . $this->translate('off');
                    break;
                case 3:
                    $parkingLight = $this->translate('left') . '=' . $this->translate('on') . ', ' . $this->translate('right') . '=' . $this->translate('off');
                    break;
                case 4:
                    $parkingLight = $this->translate('left') . '=' . $this->translate('off') . ', ' . $this->translate('right') . '=' . $this->translate('on');
                    break;
                case 5:
                    $parkingLight = $this->translate('left') . '=' . $this->translate('on') . ', ' . $this->translate('right') . '=' . $this->translate('on');
                    break;
                default:
                    $parkingLight = $this->translate('unknown value') . ' ' . $_parkingLight;
                    break;
            }
            $this->SendDebug(__FUNCTION__, 'parkingLight=' . $_parkingLight . ' => ' . $parkingLight, 0);

            $_parkingBrake = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.5.field.0.value', 0);
            switch ($_parkingBrake) {
                case 0:
                    $parkingBrake = $this->translate('inactive');
                    break;
                case 1:
                    $parkingBrake = $this->translate('active');
                    break;
                default:
                    $parkingBrake = $this->translate('unknown value') . ' ' . $_parkingBrake;
                    break;
            }
            $this->SendDebug(__FUNCTION__, 'parkingBrake=' . $_parkingBrake . ' => ' . $parkingBrake, 0);

            for ($d = 0; $d < 10; $d++) {
                for ($f = 0; $f < 99; $f++) {
                    $s = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.' . $d . '.field.' . $f . '.value', '');
                    if ($s != '') {
                        $r = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.' . $d . '.field.' . $f . '.textId', '');
                        $p = $this->GetArrayElem($jdata, 'StoredVehicleDataResponse.vehicleData.data.' . $d . '.field.' . $f . '.unit', '');
                        $this->SendDebug(__FUNCTION__, '.data.' . $d . '.field.' . $f . '=' . $s . ' ' . $r . ' ' . $p, 0);
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
        $r = $this->do_ApiCall($func, $cdata, $msg);

        /*
        $r = true;
        $cdata = '{"findCarResponse":{"Position":{"timestampCarSent":"2018-12-16T13:28:28","timestampTssReceived":"2018-12-16T12:28:28Z","carCoordinate":{"latitude":48666325,"longitude":9233152},"timestampCarSentUTC":"2018-12-16T12:28:29Z","timestampCarCaptured":"2018-12-16T13:28:28"},"parkingTimeUTC":"2018-12-16T12:28:29Z"}}';
        */
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
            if ($_lat != '') {
                $lon = substr($_lon, 0, strlen($_lon) - 6) . '.' . substr($_lon, strlen($_lon) - 6);
            } else {
                $lon = 0;
            }
            $this->SendDebug(__FUNCTION__, 'lat=' . $_lat . ' => ' . $lat . ', lon=' . $_lon . ' => ' . $lon, 0);

            $_timestampCarSent = $this->GetArrayElem($jdata, 'findCarResponse.Position.timestampCarSent', '');
            $timestampCarSent = strtotime($_timestampCarSent);
            $this->SendDebug(__FUNCTION__, 'timestampCarSent=' . $_timestampCarSent . ' => ' . date('d.m.Y H:i', $timestampCarSent), 0);

            $_parkingTime = $this->GetArrayElem($jdata, 'findCarResponse.parkingTimeUTC', '');
            $parkingTime = strtotime($_parkingTime);
            $this->SendDebug(__FUNCTION__, 'parkingTime=' . $_parkingTime . ' => ' . date('d.m.Y H:i', $parkingTime), 0);
        }
    }

    private function getClimater()
    {
        $vin = $this->ReadPropertyString('vin');
        $func = '/bs/climatisation/v1/VW/DE/vehicles/' . $vin . '/climater';

        $cdata = '';
        $msg = '';
        $r = $this->do_ApiCall($func, $cdata, $msg);
        if ($r) {
            $jdata = json_decode($cdata, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

            $_carTemp = $this->GetArrayElem($jdata, 'climater.status.temperatureStatusData.outdoorTemperature.content', '');
            if ($_carTemp != '') {
                $carTemp = (float($__carTemp) / 100.0) - 273;
            } else {
                $carTemp = 0;
            }
            $this->SendDebug(__FUNCTION__, 'carTemp=' . $_carTemp . ' => ' . $carTemp, 0);

            $climateHeatingStatus = $this->GetArrayElem($jdata, 'climater.status.climatisationStatusData.climatisationState.content', '');
            $this->SendDebug(__FUNCTION__, 'climateHeatingStatus=' . $climateHeatingStatus, 0);

            $climateHeatingWindowFrontStatus = $this->GetArrayElem($jdata, 'climater.status.windowHeatingStatusData.windowHeatingStateFront.content', '');
            $this->SendDebug(__FUNCTION__, 'climateHeatingWindowFrontStatus=' . $climateHeatingWindowFrontStatus, 0);

            $climateHeatingWindowRearStatus = $this->GetArrayElem($jdata, 'climater.status.windowHeatingStatusData.windowHeatingStateRear.content', '');
            $this->SendDebug(__FUNCTION__, 'climateHeatingWindowRearStatus=' . $climateHeatingWindowRearStatus, 0);
        }
    }

    private function getCharger()
    {
        $vin = $this->ReadPropertyString('vin');
        $func = '/bs/batterycharge/v1/VW/DE/vehicles/' . $vin . '/charger';

        $cdata = '';
        $msg = '';
        $r = $this->do_ApiCall($func, $cdata, $msg);
        if ($r) {
            $jdata = json_decode($cdata, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

            $maxChargeCurrent = $this->GetArrayElem($jdata, 'charger.settings.maxChargeCurrent.content', '');
            $this->SendDebug(__FUNCTION__, 'maxChargeCurrent=' . $maxChargeCurrent, 0);

            $chargingMode = $this->GetArrayElem($jdata, 'charger.status.chargingStatusData.chargingMode.content', '');
            $this->SendDebug(__FUNCTION__, 'chargingMode=' . $chargingMode, 0);
            $chargingReason = $this->GetArrayElem($jdata, 'charger.status.chargingStatusData.chargingReason.content', '');
            $this->SendDebug(__FUNCTION__, 'chargingReason=' . $chargingReason, 0);
            $externalPowerSupplyState = $this->GetArrayElem($jdata, 'charger.status.chargingStatusData.externalPowerSupplyState.content', '');
            $this->SendDebug(__FUNCTION__, 'externalPowerSupplyState=' . $externalPowerSupplyState, 0);
            $energyFlow = $this->GetArrayElem($jdata, 'charger.status.chargingStatusData.energyFlow.content', '');
            $this->SendDebug(__FUNCTION__, 'energyFlow=' . $energyFlow, 0);
            $chargingState = $this->GetArrayElem($jdata, 'charger.status.chargingStatusData.chargingState.content', '');
            $this->SendDebug(__FUNCTION__, 'chargingState=' . $chargingState, 0);

            $primaryEngineRange = $this->GetArrayElem($jdata, 'charger.status.cruisingRangeStatusData.primaryEngineRange.content', '');
            $this->SendDebug(__FUNCTION__, 'primaryEngineRange=' . $primaryEngineRange, 0);

            $stateOfCharge = $this->GetArrayElem($jdata, 'charger.status.batteryStatusData.stateOfCharge.content', '');
            $this->SendDebug(__FUNCTION__, 'stateOfCharge=' . $stateOfCharge, 0);
            $remainingChargingTime = $this->GetArrayElem($jdata, 'charger.status.batteryStatusData.remainingChargingTime.content', '');
            $this->SendDebug(__FUNCTION__, 'remainingChargingTime=' . $remainingChargingTime, 0);
            $remainingChargingTimeTargetSOC = $this->GetArrayElem($jdata, 'charger.status.batteryStatusData.remainingChargingTimeTargetSOC.content', '');
            $this->SendDebug(__FUNCTION__, 'remainingChargingTimeTargetSOC=' . $remainingChargingTimeTargetSOC, 0);

            $ledColor = $this->GetArrayElem($jdata, 'charger.status.ledStatusData.ledColor.content', '');
            $this->SendDebug(__FUNCTION__, 'ledColor=' . $ledColor, 0);
            $ledState = $this->GetArrayElem($jdata, 'charger.status.ledStatusData.ledState.content', '');
            $this->SendDebug(__FUNCTION__, 'ledState=' . $ledState, 0);

            $plugState = $this->GetArrayElem($jdata, 'charger.status.ledStatusData.plugState.content', '');
            $this->SendDebug(__FUNCTION__, 'plugState=' . $plugState, 0);
            $lockState = $this->GetArrayElem($jdata, 'charger.status.ledStatusData.lockState.content', '');
            $this->SendDebug(__FUNCTION__, 'lockState=' . $lockState, 0);
        }
    }
}
