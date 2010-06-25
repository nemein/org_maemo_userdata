<?php
/**
 * @package org_maemo_userdata
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Basic controller
 *
 * @package org_maemo_userdata
 */
class org_maemo_userdata_controllers_api
{
    public function __construct(midgardmvc_core_component_interface $instance)
    {
        $this->configuration = $instance->configuration;
    }

    public function get_index()
    {
        // nothing to do here
    }

    public function get_transactions(array $args)
    {
        $qb = org_maemo_userdata_transaction::new_query_builder();
        $qb->add_constraint('apiuuid', '=', $args['uuid']);

        if ($qb->count() != 1)
        {
            $this->data = array(false);
        }
        else
        {
            $result = $qb->execute();
            $since_trx = $result[0];

            $qb = org_maemo_userdata_transaction::new_query_builder();
            $qb->add_constraint('metadata.created', '>', $since_trx->metadata->created);
            // FIXME: add order
            $trxs = $qb->execute();

            $this->data = array();

            foreach ($trxs as $trx)
            {
                $user = org_maemo_userdata::userByUuid($trx->useruuid);

                $this->data[] = array(
                    'uuid'      => $trx->apiuuid,
                    'action'    => $trx->action,
                    'timestamp' => $trx->metadata->created->format(DATE_W3C),
                    'data'      => org_maemo_userdata::personToArray($user),
                );
            }
        }
    }

    public function get_userByLogin(array $args)
    {
        $qb = org_maemo_userdata_person::new_query_builder();
        $qb->add_constraint('username', '=', $args['login']);

        if ($qb->count() != 1)
        {
            $this->data = array(false);
        }
        else
        {
            $result = $qb->execute();
            $this->data = org_maemo_userdata::personToArray($result[0]);
        }
    }

    public function get_userByEmail(array $args)
    {
        $qb = org_maemo_userdata_person::new_query_builder();
        $qb->add_constraint('email', '=', $args['email']);

        if ($qb->count() != 1)
        {
            $this->data = array(false);
        }
        else
        {
            $result = $qb->execute();
            $this->data = org_maemo_userdata::personToArray($result[0]);
        }
    }

    public function get_userByUuid(array $args)
    {
        $user = org_maemo_userdata::userByUuid($args['uuid']);

        if (null === $user)
        {
            $this->data = array(false);
        }
        else
        {
            $this->data = org_maemo_userdata::personToArray($user);
        }
    }

    public function post_userByUuid(array $args)
    {
        $raw = file_get_contents('php://input');
        $user_data = json_decode($raw, true);

        $user = org_maemo_userdata::userByUuid($args['uuid']);

        if (null === $user)
        {
            $this->data = array(false);
        }
        else
        {
            $_allowed = org_maemo_userdata::getListOfUserFields();
            foreach ($user_data as $k => $v)
            {
                if (in_array($k, $_allowed))
                {
                    $user->$k = $v;
                }
            }
            $user->update();

            org_maemo_userdata::registerTransaction($user, 'update');

            $this->data = array(true);
        }
    }

    public function post_createUser(array $args)
    {
        $raw = file_get_contents('php://input');
        $user_data = json_decode($raw, true);

        if (!isset($user_data['username']) or !isset($user_data['password']))
        {
            $this->data = array(false);
            return;
        }

        $qb = org_maemo_userdata_person::new_query_builder();
        $qb->add_constraint('username', '=', $user_data['username']);

        if ($qb->count() > 0)
        {
            // already added. should we update, instead?
            $this->data = array(false);
            return;
        }

        $obj = new org_maemo_userdata_person();
        $obj->apiuuid = org_maemo_userdata::generate_UUID();
        $_allowed = org_maemo_userdata::getListOfUserFields();
        foreach ($user_data as $k => $v)
        {
            if (in_array($k, $_allowed))
            {
                $obj->$k = $v;
            }
        }
        $obj->create();

        org_maemo_userdata::registerTransaction($obj, 'create');

        $this->data = array('uuid' => $obj->apiuuid);
    }
}
