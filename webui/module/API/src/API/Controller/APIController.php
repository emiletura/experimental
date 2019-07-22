<?php

/**
 *
 * bareos-webui - Bareos Web-Frontend
 *
 * @link      https://github.com/bareos/bareos-webui for the canonical source repository
 * @copyright Copyright (c) 2013-2019 Bareos GmbH & Co. KG (http://www.bareos.org/)
 * @license   GNU Affero General Public License (http://www.gnu.org/licenses/)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace API\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Json\Json;
use Zend\Http\Response;

class APIController extends AbstractActionController
{
    protected $apiModel = null;
    protected $bsock = null;

    private $requestObject = null;
    private $command = null;
    private $resultObject = null;

    public function indexAction()
    {
        $this->RequestURIPlugin()->setRequestURI();

        if (!$this->SessionTimeoutPlugin()->isValid()) {
            return $this->redirect()->toRoute(
              'auth',
              array(
              'action' => 'login'
            ),
              array(
              'query' => array(
                'req'  => $this->RequestURIPlugin()->getRequestURI(),
                'dird' => $_SESSION['bareos']['director']
              )
            )
          );
        }

        $response = $this->getResponse();

        if (!$this->getRequest()->isPost()) {
            $response->setStatusCode(Response::STATUS_CODE_404);
        } else {
            $this->bsock = $this->getServiceLocator()->get('director');
            $this->setRequestObject($this->getRequest()->getContent());
            $this->setCommand();
            $this->setResultObject();
            $response->getHeaders()->addHeaderLine(
                'Content-Type',
                'application/json'
            );
            $response->setContent($this->resultObject);
        }

        return $response;
    }

    private function setRequestObject($requestObjectData)
    {
        $this->requestObject = Json::decode($requestObjectData);
    }

    private function setResultObject()
    {
        $model = $this->getAPIModel();
        $this->resultObject = $model->executeCommand($this->bsock, $this->command);
    }

    private function setCommand()
    {
        $this->command .= $this->requestObject->method . ' ';

        if (array_key_exists("subcommand", $this->requestObject->params)) {
            $this->command .= $this->requestObject->params->subcommand . ' ';
        }

        foreach ($this->requestObject->params as $key => $value) {
            $this->command .= $key . '="' . $value .'" ';
        }
    }

    public function getAPIModel()
    {
        if (!$this->apiModel) {
            $sm = $this->getServiceLocator();
            $this->apiModel = $sm->get('API\Model\APIModel');
        }
        return $this->apiModel;
    }
}
