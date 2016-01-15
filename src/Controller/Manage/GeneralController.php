<?php

/*
 * This file is part of the Eventum (Issue Tracking System) package.
 *
 * @copyright (c) Eventum Team
 * @license GNU General Public License, version 2 or later (GPL-2+)
 *
 * For the full copyright and license information,
 * please see the COPYING and AUTHORS files
 * that were distributed with this source code.
 */

namespace Eventum\Controller\Manage;

use Misc;
use Project;
use Setup;
use User;

class GeneralController extends ManageBaseController
{
    /** @var string */
    protected $tpl_name = 'manage/general.tpl.html';

    /** @var int */
    protected $min_role = User::ROLE_REPORTER;

    /** @var string */
    private $cat;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $request = $this->getRequest();

        $this->cat = $request->request->get('cat') ?: $request->query->get('cat');
    }

    /**
     * @inheritdoc
     */
    protected function defaultAction()
    {
        if ($this->cat == 'update') {
            $this->updateAction();
        }
    }

    private function updateAction()
    {
        $post = $this->getRequest()->request;

        $smtp = $post->get('smtp');
        $smtp['auth'] = (bool)$smtp['auth'];
        $setup = array(
            'tool_caption' => $post->get('tool_caption'),
            'support_email' => $post->get('support_email'),
            'description_email_0' => $post->get('description_email_0'),
            'spell_checker' => $post->get('spell_checker'),
            'irc_notification' => $post->get('irc_notification'),
            'update' => $post->getBoolean('update'),
            'closed' => $post->getBoolean('closed'),
            'emails' => $post->getBoolean('emails'),
            'files' => $post->getBoolean('files'),
            'smtp' => $smtp,
            'open_signup' => $post->get('open_signup'),
            'accounts_projects' => $post->get('accounts_projects'),
            'accounts_role' => $post->get('accounts_role'),
            'subject_based_routing' => $post->get('subject_based_routing'),
            'email_routing' => $post->get('email_routing'),
            'note_routing' => $post->get('note_routing'),
            'draft_routing' => $post->get('draft_routing'),
            'email_error' => $post->get('email_error'),
            'email_reminder' => $post->get('email_reminder'),
            'handle_clock_in' => $post->get('handle_clock_in'),
            'relative_date' => $post->get('relative_date'),
        );
        $res = Setup::save($setup);
        $this->tpl->assign('result', $res);

        $map = array(
            1 => array(ev_gettext('Thank you, the setup information was saved successfully.'), Misc::MSG_INFO),
            -1 => array(ev_gettext(
                            "ERROR: The system doesn't have the appropriate permissions to create the configuration file in the setup directory (%1\$s). " .
                            'Please contact your local system administrator and ask for write privileges on the provided path.',
                            APP_CONFIG_PATH
                        ),
                        Misc::MSG_NOTE_BOX),
            -2 => array(ev_gettext(
                            "ERROR: The system doesn't have the appropriate permissions to update the configuration file in the setup directory (%1\$s). " .
                            'Please contact your local system administrator and ask for write privileges on the provided filename.',
                            APP_SETUP_FILE
                        ),
                        Misc::MSG_NOTE_BOX),
        );
        Misc::mapMessages($res, $map);
    }

    /**
     * @inheritdoc
     */
    protected function prepareTemplate()
    {
        $this->tpl->assign(
            array(
                'project_list' => Project::getAll(),
                'setup' => Setup::get(),
                'user_roles' => User::getRoles(array('Customer')),
            )
        );
    }
}
