<?php

sendQueuedRequests();


function sendQueuedRequests()
{
    // TODO: execute this method! ;)
    $qb = org_maemo_userdata_webhooks_queue::new_query_builder();
    $qb->add_order('id');
    $queued = $qb->execute();

    $stuck_urls = array();

    foreach ($queued as $item) {
        if (!in_array($item->url, $stuck_urls)) {
            echo "sending item #".$item->id.' to "'.$item->url.'"'."\n";

            try {
                $res = _sendHttpRequest($item->url, $item->payload);

                if (false === $res) {
                    throw new Exception();
                } else {
                    $item->delete();
                    echo "-> ok\n";
                }
            } catch (Exception $e) {
                // error. will retry later. blocking this webhook for now
                $stuck_urls[] = $item->url;
                echo "-> url didn't answer in a meaningful way. skipping it for now\n";
            }
        }
    }
}

function _sendHttpRequest($url, $json)
{
    $ch = curl_init();

    $res = curl_setopt($ch, CURLOPT_URL, $url);

    if (strpos($url, 'https://') === 0) {
        throw new Exception('FIXME');
        $res = curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $res = curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        // $res = curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
        $res = curl_setopt($ch, CURLOPT_SSLKEY, dirname(__FILE__).'/configuration/client.key');
        $res = curl_setopt($ch, CURLOPT_SSLKEYPASSWD, 'password');
        $res = curl_setopt($ch, CURLOPT_SSLCERT, dirname(__FILE__).'/configuration/client.cer');
        $res = curl_setopt($ch, CURLOPT_SSLCERTPASSWD, 'password');
    }

    $res = curl_setopt($ch, CURLOPT_HEADER, 0);
    $res = curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $res = curl_setopt($ch, CURLOPT_POST,           1);
    $res = curl_setopt($ch, CURLOPT_POSTFIELDS,     $json);
    $res = curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: application/json'));

    // grab URL and pass it to the browser
    $result = curl_exec($ch);

    if (false === $result) {
        echo curl_error($ch)."\n";
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $arr = json_decode($result);

    if (is_array($arr) and is_bool($arr[0]))
        return $arr[0];
    elseif (is_bool($arr))
        return $arr;

    throw new Exception('Unexpected response: '.$result);
}
