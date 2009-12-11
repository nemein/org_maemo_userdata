<?php
/**
 * @package org_maemo_userdata
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Basic component
 *
 * @package org_maemo_userdata
 */
class org_maemo_userdata extends midgardmvc_core_component_baseclass
{
    /**
     * @brief Generates a Universally Unique IDentifier, version 4.
     *
     * This function generates a truly random UUID. The built in CakePHP String::uuid() function
     * is not cryptographically secure. You should uses this function instead.
     *
     * @see http://tools.ietf.org/html/rfc4122#section-4.4
     * @see http://en.wikipedia.org/wiki/UUID
     * @return string A UUID, made up of 32 hex digits and 4 hyphens.
     * @author sean at seancolombo dot com (via http://docs.php.net/uniqid#88023)
     */
    public static function generate_UUID()
    {
        $pr_bits = null;
        $fp = @fopen('/dev/urandom','rb');

        if ($fp !== false)
        {
            $pr_bits .= @fread($fp, 16);
            @fclose($fp);
        }
        else
        {
            // If /dev/urandom isn't available (eg: in non-unix systems), use mt_rand().
            $pr_bits = "";
            for ($cnt=0; $cnt < 16; $cnt++)
            {
                $pr_bits .= chr(mt_rand(0, 255));
            }
        }

        $time_low = bin2hex(substr($pr_bits,0, 4));
        $time_mid = bin2hex(substr($pr_bits,4, 2));
        $time_hi_and_version = bin2hex(substr($pr_bits,6, 2));
        $clock_seq_hi_and_reserved = bin2hex(substr($pr_bits,8, 2));
        $node = bin2hex(substr($pr_bits,10, 6));

        /**
         * Set the four most significant bits (bits 12 through 15) of the
         * time_hi_and_version field to the 4-bit version number from
         * Section 4.1.3.
         * @see http://tools.ietf.org/html/rfc4122#section-4.1.3
         */
        $time_hi_and_version = hexdec($time_hi_and_version);
        $time_hi_and_version = $time_hi_and_version >> 4;
        $time_hi_and_version = $time_hi_and_version | 0x4000;

        /**
         * Set the two most significant bits (bits 6 and 7) of the
         * clock_seq_hi_and_reserved to zero and one, respectively.
         */
        $clock_seq_hi_and_reserved = hexdec($clock_seq_hi_and_reserved);
        $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved >> 2;
        $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved | 0x8000;

        return sprintf(
            '%08s-%04s-%04x-%04x-%012s',
            $time_low, $time_mid, $time_hi_and_version,
            $clock_seq_hi_and_reserved,
            $node
        );
    }

    public static function userByUuid($uuid)
    {
        $qb = org_maemo_userdata_person::new_query_builder();
        $qb->add_constraint('apiuuid', '=', $uuid);

        if ($qb->count() != 1)
        {
            return null;
        }
        else
        {
            $result = $qb->execute();
            return $result[0];
        }
    }

    public static function getListOfUserFields()
    {
        // seems like midgard doesn't export these dynamically.
        // so, list needs to be manually updated
        return array(
            "morgid",
            "garageid",
            "talkid",
            "username",
            "password",
            "joindate",
            "title",
            "firstname",
            "lastname",
            "birthdate",
            "street",
            "postcode",
            "city",
            "country",
            "ccode",
            "email",
            "phone",
            "fax",
            "homepage",
            "jabber",
            "icq",
            "aim",
            "yahoo",
            "msn",
            "skype",
        );
    }

    public static function personToArray(org_maemo_userdata_person $person)
    {
        $fields = org_maemo_userdata::getListOfUserFields();

        $result = array('uuid' => $person->apiuuid);

        foreach ($fields as $field)
        {
            $value = $person->$field;

            if ($value instanceof midgard_datetime)
            {
                $result[$field] = $value->format(DATE_W3C);
            }
            else
            {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    public static function registerTransaction(org_maemo_userdata_person $person, $action)
    {
        $trx = new org_maemo_userdata_transaction();
        $trx->apiuuid = org_maemo_userdata::generate_UUID();
        $trx->useruuid = $person->apiuuid;
        $trx->action = $action;
        $trx->create();

        self::broadcastTransaction($trx);
    }

    private static function broadcastTransaction(org_maemo_userdata_transaction $trx)
    {
        $cfg = midgardmvc_core::get_instance()->configuration;

        $user = self::userByUuid($trx->useruuid);
        $data = json_encode(array(
            'uuid'      => $trx->apiuuid,
            'action'    => $trx->action,
            'timestamp' => $trx->created->format(DATE_W3C),
            'data'      => self::personToArray($user),
        ));

        foreach ($cfg->webhooks as $webhook) {
            $que = new org_maemo_userdata_webhooks_queue();
            $que->url = $webhook['url'];
            $que->payload = $data;
            $que->create();
        }
    }

    public static function sendQueuedRequests()
    {
        // TODO: execute this method! ;)
        $qb = org_maemo_userdata_webhooks_queue::new_query_builder();
        $qb->add_order('id');
        $queued = $qb->execute();

        $stuck_urls = array();

        foreach ($queued as $item) {
            if (!in_array($item->url, $stuck_urls)) {
                $res = self::sendHttpRequest($item->url, $item->payload);

                if (false === $res) {
                    // error. will retry later. blocking this webhook for now
                    $stuck_urls[] = $item->url;
                } else {
                    $item->delete();
                }
            }
        }
    }

    private static function sendHttpRequest($url, $json)
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
}
