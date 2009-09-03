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
class org_maemo_userdata_controllers_index
{
    public function __construct($instance)
    {
        $this->configuration = $instance->configuration;
    }

    public function get_transactions($route_id, &$data, $args)
    {
    }

    public function get_userByLogin($route_id, &$data, $args)
    {
    }

    public function get_userByEmail($route_id, &$data, $args)
    {
    }

    public function get_userByUuid($route_id, &$data, $args)
    {
    }

    public function post_userByUuid($route_id, &$data, $args)
    {
    }

    public function post_createUser($route_id, &$data, $args)
    {
    }
}
