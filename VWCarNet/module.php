<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

class VWCarNet extends IPSModule
{
    use VWCarNetCommon;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('userid', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('vin', '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $userid = $this->ReadPropertyString('userid');
        $password = $this->ReadPropertyString('password');

        if ($userid != '' && $password != '') {
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->SetStatus(IS_INVALIDCONFIG);
        }
    }

    public function GetConfigurationForm()
    {
        $formElements = [];
        $formElements[] = ['type' => 'Label', 'label' => 'Volkswagen Car-Net Account'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'userid', 'caption' => 'User-ID (email)'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'password', 'caption' => 'Password'];
        $formElements[] = ['type' => 'Label', 'label' => ''];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'vin', 'caption' => 'VIN'];

        $formActions = [];
        $formActions[] = ['type' => 'Button', 'caption' => 'Test access', 'onClick' => 'VWCarNet_TestAccess($id);'];
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

    public function TestAccess()
    {
        $txt = '';

        $cdata = '';
        $msg = '';
        $r = $this->do_ApiCall('/usermanagement/users/v1/VW/DE/vehicles/', $cdata, $msg);
        if ($r == false) {
            $txt .= $this->translate('invalid account-data') . PHP_EOL;
            $txt .= PHP_EOL;
            if ($msg != '') {
                $txt .= $this->translate('message') . ': ' . $msg . PHP_EOL;
            }
        } else {
            $txt = $this->translate('valid account-data') . PHP_EOL;
        }

        echo $txt;
    }

    private function getToken(&$msg)
    {
        $userid = $this->ReadPropertyString('userid');
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

            $jdata = do_HttpRequest($auth_url, '', $header, $postdata, 'POST');
            $token = $jdata['access_token'];

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
            //$expires_in = $jdata['expires_in'];
            $expires_in = 0;

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
        $statuscode = $this->do_HttpRequest($func, $params, $header, '', 'GET', $data, $msg);
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

        echo 'http-' . $mode . ': url=' . $url . PHP_EOL;
        echo '    header=' . print_r($header, true) . PHP_EOL;
        if ($postdata != '') {
            $postdata = http_build_query($postdata);
            echo '    postdata=' . $postdata . PHP_EOL;
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

        echo ' => httpcode=' . $httpcode . ', duration=' . $duration . 's' . PHP_EOL;
        echo ' => cdata=' . $cdata . PHP_EOL;
        $jdata = json_decode($cdata, true);
        echo ' => jdata=' . print_r($jdata, true) . PHP_EOL;

        $statuscode = 0;
        $err = '';
        $msg = '';
        $data = '';

        // 200 = ok
        // 400 = bad request
        // 401 = unauthorized
        // 404 = not found
        // 405 = method not allowed
        // 500 = internal server error
        // 503 = unavailable

        if ($cdata != '') {
            $jdata = json_decode($cdata, true);
            if (isset($jdata['message'])) {
                $msg = $jdata['message'];
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
}
