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
    public function __construct(midcom_core_component_interface $instance)
    {
        $this->configuration = $instance->configuration;
    }

    public function get_index()
    {
    }

    public function get_transactions(array $args)
    {
    }

    public function get_userByLogin(array $args)
    {
    }

    public function get_userByEmail(array $args)
    {
    }

    public function get_userByUuid(array $args)
    {
    }

    public function post_userByUuid(array $args)
    {
    }

    public function post_createUser(array $args)
    {
    }
}
