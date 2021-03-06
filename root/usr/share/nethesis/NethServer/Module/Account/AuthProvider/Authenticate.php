<?php

namespace NethServer\Module\Account\AuthProvider;

/*
 * Copyright (C) 2016 Nethesis Srl
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

use Nethgui\System\PlatformInterface as Validate;

/**
 * Description of Authenticate
 *
 * @author Davide Principi <davide.principi@nethesis.it>
 */
class Authenticate extends \Nethgui\Controller\AbstractController implements \Nethgui\Component\DependencyConsumer
{

    public function initialize()
    {
        parent::initialize();
        $this->declareParameter('login', Validate::NOTEMPTY);
        $this->declareParameter('password', Validate::NOTEMPTY);
    }

    public function process()
    {
        parent::process();
        if ( ! $this->getRequest()->isMutation()) {
            return;
        }

        $domain = \Nethgui\array_end(\explode('.', \gethostname(), 2));

        $ph = popen('/usr/bin/sudo /usr/sbin/realm join ' . $domain, 'w');
        fwrite($ph, $this->parameters['password'] . "\n");
        $err = pclose($ph);

        if ($err === 0) {
            $this->getPlatform()->getDatabase('configuration')->setProp('sssd', array('status' => 'enabled'));
            $this->getPlatform()->signalEvent('nethserver-sssd-save');
            $this->nextModule = 'Index';
        } else {
            $this->joinError = TRUE;
            $this->getLog()->error("[ERROR] exit code from realm join operation is $err");
        }
    }

    public function prepareView(\Nethgui\View\ViewInterface $view)
    {
        parent::prepareView($view);
        if ($this->getRequest()->isMutation()) {
            if ($this->joinError === TRUE) {
                $this->notifications->error('Invalid credentials');
            }
        } elseif ( ! $view['login']) {
            $view['login'] = 'Administrator';
        }
    }

    public function nextPath()
    {
        return isset($this->nextModule) ? $this->nextModule : parent::nextPath();
    }

    public function setUserNotifications(\Nethgui\Model\UserNotifications $n)
    {
        $this->notifications = $n;
        return $this;
    }

    public function getDependencySetters()
    {
        return array('UserNotifications' => array($this, 'setUserNotifications'));
    }

}
