<?php
/**
 * Shout:: defines an set of utility methods for the Shout application.
 *
 * Copyright 2005-2010 Alkaloid Networks LLC (http://projects.alkaloid.net)
 *
 * See the enclosed file COPYING for license information (BSD). If you
 * did not receive this file, see
 * http://www.opensource.org/licenses/bsd-license.php.
 *
 * @author  Ben Klang <ben@alkaloid.net>
 * @package Shout
 */
class Shout
{
    var $applist = array();
    var $_applist_curapp = '';
    var $_applist_curfield = '';

    /**
     * Build Shout's list of menu items.
     *
     * @access public
     */
    static public function getMenu($returnType = 'object')
    {
        $mask = Horde_Menu::MASK_PROBLEM | Horde_Menu::MASK_LOGIN;
        $menu = new Horde_Menu($mask);

        $menu->add(Horde::applicationUrl('dialplan.php'), _("Call Menus"), "dialplan.png");
        $menu->add(Horde::applicationUrl('recordings.php'), _("Recordings"), "recordings.png");
        $menu->add(Horde::applicationUrl('extensions.php'), _("Extensions"), "extension.png");
        $menu->add(Horde::applicationUrl('devices.php'), _("Devices"), "shout.png");
        $menu->add(Horde::applicationUrl('conferences.php'), _("Conferences"), "conference.png");

        /* Administration. */
        if (Horde_Auth::isAdmin('shout:admin')) {
            $menu->add(Horde::applicationUrl('admin.php'), _("_Admin"), 'admin.png');
        }

        if ($returnType == 'object') {
            return $menu;
        } else {
            return $menu->render();
        }
    }

    /**
     * Checks for the given permissions for the current user on the given
     * permission.  Optionally check for higher-level permissions and ultimately
     * test for superadmin priveleges.
     *
     * @param string $permname Name of the permission to check
     *
     * @param optional int $permmask Bitfield of permissions to check for
     *
     * @param options int $numparents Check for the same permissions this
     *                                many levels up the tree
     *
     * @return boolean the effective permissions for the user.
     */
    static public function checkRights($permname, $permmask = null, $numparents = 0)
    {
        if (Horde_Auth::isAdmin()) { return true; }

        if ($permmask === null) {
            $permmask = Horde_Perms::SHOW | Horde_Perms::READ;
        }

        # Default deny all permissions
        $user = 0;
        $superadmin = 0;

        $perms = $GLOBALS['injector']->getInstance('Horde_Perms');
        $superadmin = $perms->hasPermission('shout:superadmin',
            Horde_Auth::getAuth(), $permmask);

        while ($numparents >= 0) {
            $tmpuser = $perms->hasPermission($permname,
                Horde_Auth::getAuth(), $permmask);

            $user = $user | $tmpuser;
            if ($numparents > 0) {
                $pos = strrpos($permname, ':');
                if ($pos) {
                    $permname = substr($permname, 0, $pos);
                }
            }
            $numparents--;
        }
        $test = $superadmin | $user;

        return ($test & $permmask) == $permmask;
    }

    /**
     * Generate new device authentication tokens.
     *
     * This method is designed to generate random strings for the
     * authentication ID and password.  The result is intended to be used
     * for automatically generated device information.  The user is prevented
     * from specifying usernames and passwords for these reasons:
     * 1) If a username and/or password can be easily guessed, monetary loss
     *    is likely through the fraudulent placing of telephone calls.
     *    This has been observed in the wild far too many times already.
     *
     * 2) The username and password are only needed to be programmed into the
     *    device once, and then stored semi-permanently.  In some cases, the
     *    provisioning can be done automatically.  For these reasons, having
     *    user-friendly usernames and passswords is not terribly important.
     *
     * @param string $account  Account for this credential pair
     *
     * @return array  Array of (string $deviceID, string $devicePassword)
     */
    static public function genDeviceAuth($account)
    {
        $devid = $account . substr(uniqid(), 6);

        // This simple password generation algorithm inspired by Jon Haworth
        // http://www.laughing-buddha.net/jon/php/password/

        // define possible characters
        // Vowels excluded to avoid potential pronounceability
        $possible = "0123456789bcdfghjkmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ";

        $password = "";
        $i = 0;
        while ($i < 12) {
            // pick a random character from the possible ones
            $char = substr($possible, mt_rand(0, strlen($possible)-1), 1);

            // we don't want this character if it's already in the password
            if (!strstr($password, $char)) {
              $password .= $char;
              $i++;
            }
        }

        return array($devid, $password);
    }

    static public function getMenuActions()
    {
        $shout = $GLOBALS['registry']->getApiInstance('shout', 'application');
        $account = $_SESSION['shout']['curaccount'];

        return array(
            'jump' => array(
                'description' => _("Jump to menu"),
                'args' => array (
                    'menuName' => array(
                        'name' => _("Menu"),
                        'type' => 'enum',
                        'required' => true,
                        'params' => array(self::getNames($shout->dialplan->getMenus($account)))
                    )
                )
            ),
            'ringexten' => array(
                'description' => _("Ring extension"),
                'args' => array(
                    'exten' => array(
                        'name' => _("Extension"),
                        'type' => 'enum',
                        'required' => true,
                        'params' => array(self::getNames($shout->extensions->getExtensions($account)))
                    )
                )
            ),
            'leave_message' => array(
                'description' => _("Go to voicemail"),
                'args' => array(
                    'exten' => array(
                        'name' => _("Mailbox"),
                        'type' => 'enum',
                        'required' => true,
                        'params' => array(self::getNames($shout->extensions->getExtensions($account)))
                    )
                )
            ),
            'conference' => array(
                'description' => _("Enter conference"),
                'args' => array(
                    'roomno' => array(
                        'name' => _("Room Number (optional)"),
                        'type' => 'number',
                        'required' => false
                    )
                )
            ),
            'directory' => array(
                'description' => _("Company directory"),
                'args' => array()
            ),
            'dial' => array(
                'description' => _("Call out"),
                'args' => array(
                    'numbers' => array(
                        'name' => _("Phone Number"),
                        'type' => 'phone',
                        'required' => true
                    )
                )
            ),
            'rewind' => array(
                'description' => _("Restart menu"),
                'args' => array()
            ),
            'adminlogin' => array(
                'description' => _("Login to Admin Functions"),
                'args' => array()
            ),
            'none' => array(
                'description' => _("No action"),
                'args' => array()
            )

            // TODO: Actions to implement: Queue, VoicemailLogin
        );
    }

    static public function getNames($array)
    {
        $res = array();
        foreach ($array as $id => $info) {
            $res[$id] = $info['name'];
        }
        return $res;
    }

}
