<?php
/**
 * Copyright 2010 Alkaloid Networks LLC (http://projects.alkaloid.net)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see
 * http://www.opensource.org/licenses/bsd-license.php.
 *
 * @package Shout
 */

class ConferenceDetailsForm extends Horde_Form {

    function __construct(&$vars)
    {
        if ($vars->exists('roomno')) {
            $formtitle = "Edit Conference Room";
            $roomno = $vars->get('roomno');
            $this->addHidden('', 'oldroomno', 'text', true);
            $vars->set('oldroomno', $roomno);
            $edit = true;
        } else {
            $formtitle = "Create Conference Room";
            $edit = false;
        }

        $curaccount = $_SESSION['shout']['curaccount'];
        $accountname = $vars->account;
        $title = sprintf(_("$formtitle - Account: %s"), $accountname);
        parent::__construct($vars, $title);

        $this->addHidden('', 'action', 'text', true);
        $this->addVariable(_("Room Name"), 'name', 'text', true);
        $this->addVariable(_("Room Number"), 'roomno', 'number', true);
        $this->addVariable(_("PIN"), 'pin', 'number', false);
        return true;
    }

    public function execute()
    {
        $shout = $GLOBALS['registry']->getApiInstance('shout', 'application');

        $action = $this->_vars->get('action');
        $account = $this->_vars->get('account');
        $roomno = $this->_vars->get('roomno');
        $details = array(
            'name' => $this->_vars->get('name'),
            'pin' => $this->_vars->get('pin')
        );


        // For safety, we force the device ID and password rather than rely
        // on the form to pass them around.
        if ($action != 'add') { // $action must be 'edit'
            $oldroomno = $this->_vars->get('oldroomno');
            $conferences = $shout->storage->getConferences($account);
            if (!isset($conferences[$roomno])) {
                // The device requested doesn't already exist.  This can't
                // be a valid edit.
                throw new Shout_Exception(_("That conference room does not exist."),
                                            'horde.error');
            }
            $details['oldroomno'] = $oldroomno;
        }

        $shout->storage->saveConference($account, $roomno, $details);
    }

}

class ConferenceDeleteForm extends Horde_Form
{
    function __construct(&$vars)
    {
        $devid = $vars->get('devid');
        $account = $vars->get('account');

        $title = _("FIXME Delete Device %s - Account: %s");
        $title = sprintf($title, $devid, $_SESSION['shout']['accounts'][$account]);
        parent::__construct($vars, $title);

        $this->addHidden('', 'account', 'text', true);
        $this->addHidden('', 'devid', 'text', true);
        $this->addHidden('', 'action', 'text', true);
        $this->setButtons(array(_("Delete"), _("Cancel")));
    }

    function execute()
    {
        $shout = $GLOBALS['registry']->getApiInstance('shout', 'application');
        $account = $this->_vars->get('account');
        $devid = $this->_vars->get('devid');
        $shout->devices->deleteDevice($account, $devid);
    }
}