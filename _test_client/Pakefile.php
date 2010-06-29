<?php

pake_desc('Create new user. usage: pake c "username" "password"');
pake_task('create_user');

pake_desc('Get user by uuid. usage pake g "uuid"');
pake_task('get_user');

pake_desc('Update user. usage: pake u "uuid" "key" "new_value"');
pake_task('update_user');

pake_desc('Import users from yaml. usage: pake i "users.yml"');
pake_task('import_users');


function run_create_user($task, $args)
{
    pake_echo_action('user+', _create_user($args[0], $args[1]));
}

function run_get_user($task, $args, $params)
{
    if (isset($params['uuid']) or empty($params)) {
        pake_echo_comment('looking by uuid');
        $uuid = $args[0];
        $data = getFromOink('/user/'.$uuid.'/');
    } elseif (isset($params['login'])) {
        pake_echo_comment('looking by login');
        $login = $args[0];
        $data = getFromOink('/user/by_login/'.$login.'/');
    } elseif (isset($params['email'])) {
        pake_echo_comment('looking by email');
        $email = $args[0];
        $data = getFromOink('/user/by_email/'.$email.'/');
    }

    $str = json_decode($data);

    var_dump($str);
}

function run_update_user($task, $args)
{
    $uuid = $args[0];
    $key = $args[1];
    $value = $args[2];

    $json = postToOink('/user/'.$uuid.'/', array($key => $value));
    $result = json_decode($json);

    if ($result[0] === true)
        pake_echo_action('user+', $uuid);
    else
        pake_echo_error($json);
}

function run_import_users($task, $args)
{
    if (!isset($args[0])) {
        pake_echo_error('usage: pake i "users.yml"');
        return false;
    }

    pake_echo_comment("Reading file. It can take awhile…");
    $str = file_get_contents($args[0]);
    $data = pakeYaml::loadString($str);

    pake_echo_comment("Starting import…");
    $len = count($data);
    for ($i = 0; $i < $len; $i++) {
        $row = $data[$i];

        if (_create_user($row['login'], $row['password'], $row['data'])) {
            pake_echo_action('user+', "({$i} of {$len}) ".$row['login']);
        } else {
            pake_echo_comment('already exists: '."({$i} of {$len}) ".$row['login']);
        }
    }
}

// HELPERS
// -> high-level
function _create_user($login, $password, array $fields = array())
{
    $fields['username'] = $login;
    $fields['password'] = hash('sha256', $password);

    $json = postToOink('/user/', $fields);
    $result = json_decode($json);

    return $result->uuid;
}

// -> low-level
function postToOink($url, array $data)
{
    $ch = curl_init();

    $res = curl_setopt($ch, CURLOPT_URL, 'https://oink.nrln.net:8080'.$url);

    $res = curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $res = curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    // $res = curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
    $res = curl_setopt($ch, CURLOPT_SSLKEY, dirname(__FILE__).'/client.key');
    $res = curl_setopt($ch, CURLOPT_SSLKEYPASSWD, 'FnZ19');
    $res = curl_setopt($ch, CURLOPT_SSLCERT, dirname(__FILE__).'/client.cer');
    $res = curl_setopt($ch, CURLOPT_SSLCERTPASSWD, 'FnZ19');

    $res = curl_setopt($ch, CURLOPT_HEADER, 0);
    $res = curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $res = curl_setopt($ch, CURLOPT_POST,           1);
    $res = curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($data));
    $res = curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: application/json'));

    // grab URL and pass it to the browser
    $result = curl_exec($ch);

    if (false === $result) {
        $ex = new pakeException(curl_error($ch));
        curl_close($ch);
        throw $ex;
    }

    curl_close($ch);

    return $result;
}

function getFromOink($url)
{
    $ch = curl_init();

    $res = curl_setopt($ch, CURLOPT_URL, 'https://oink.nrln.net:8080'.$url);

    $res = curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $res = curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    // $res = curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
    $res = curl_setopt($ch, CURLOPT_SSLKEY, dirname(__FILE__).'/client.key');
    $res = curl_setopt($ch, CURLOPT_SSLKEYPASSWD, 'FnZ19');
    $res = curl_setopt($ch, CURLOPT_SSLCERT, dirname(__FILE__).'/client.cer');
    $res = curl_setopt($ch, CURLOPT_SSLCERTPASSWD, 'FnZ19');

    $res = curl_setopt($ch, CURLOPT_HEADER, 0);
    $res = curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // grab URL and pass it to the browser
    $result = curl_exec($ch);

    if (false === $result) {
        echo curl_error($ch)."\n";
    }

    curl_close($ch);

    return $result;
}
