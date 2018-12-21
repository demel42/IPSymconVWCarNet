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
            if (isset($jdata['"error"'])) {
                $msg = $jdata['description'];
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
            echo 'url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err . PHP_EOL;
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
        if ($r) {
            $jdata = json_decode($cdata, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        }
    }

    private function getPosition()
    {
        $vin = $this->ReadPropertyString('vin');
        $func = '/bs/cf/v1/VW/DE/vehicles/' . $vin . '/position';

        $cdata = '';
        $msg = '';
        $r = $this->do_ApiCall($func, $cdata, $msg);
        if ($r) {
            $jdata = json_decode($cdata, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
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
        }
    }
}
