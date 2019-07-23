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
  private $resultObject = null;

  public function indexAction()
  {
    $this->RequestURIPlugin()->setRequestURI();

    if ( ! $this->SessionTimeoutPlugin()->isValid()) {
      return $this->redirect()->toRoute(
        'auth',
        array(
          'action' => 'login',
        ),
        array(
          'query' => array(
            'req'  => $this->RequestURIPlugin()->getRequestURI(),
            'dird' => $_SESSION['bareos']['director'],
          ),
        )
      );
    }

    $response = $this->getResponse();

    if ( ! $this->getRequest()->isPost()) {
      $response->setStatusCode(Response::STATUS_CODE_404);
    } else {
      $this->bsock         = $this->getServiceLocator()->get('director');
      $this->requestObject = $this->decodeRequestObject($this->getRequest()->getContent());

      $commandObjects = $this->generateListJobsJobstatusCommandObjects($this->requestObject);

      $responseObjects = array();
      foreach ($commandObjects as $commandObject) {
        $command = $this->parseRequestObject($commandObject);
        array_push($responseObjects, JSON::decode($this->executeCommand($command)));
      }

      $mergedResults = array();
      foreach ($responseObjects as $responseObject) {
        array_push($mergedResults, $responseObject->result);
      }

      $response->getHeaders()->addHeaderLine(
        'Content-Type',
        'application/json'
      );
      $response->setContent($this->resultObject);
    }

    return $response;
  }

  private function decodeRequestObject($requestObjectData)
  {
    return Json::decode($requestObjectData);
  }

  private function executeCommand($command)
  {
    $model = $this->getAPIModel();

    return $model->executeCommand($this->bsock, $command);
  }

  private function parseRequestObject($commandObject)
  {
    $command = $commandObject->method.' ';

    if (isset($this->requestObject->params->subcommand)) {
      $command .= $commandObject->params->subcommand.' ';
    }


    foreach ($commandObject->params as $key => $value) {
      if ($key !== "subcommand") {
        $command .= $key.'="'.$value.'" ';
      }
    }

    return $command;
  }

  public function getAPIModel()
  {
    if ( ! $this->apiModel) {
      $sm             = $this->getServiceLocator();
      $this->apiModel = $sm->get('API\Model\APIModel');
    }

    return $this->apiModel;
  }

  public function getJobsByStatus(&$bsock = null, $jobname = null, $status = null, $days = null, $hours = null)
  {
    if (isset($bsock, $status)) {
      if (isset($days)) {
        if ($days == "all") {
          $cmd = 'llist jobs jobstatus='.$status.'';
        } else {
          $cmd = 'llist jobs jobstatus='.$status.' days='.$days.'';
        }
      } elseif (isset($hours)) {
        if ($hours == "all") {
          $cmd = 'llist jobs jobstatus='.$status.'';
        } else {
          $cmd = 'llist jobs jobstatus='.$status.' hours='.$hours.'';
        }
      } else {
        $cmd = 'llist jobs jobstatus='.$status.'';
      }
      if ($jobname != "all") {
        $cmd .= ' jobname="'.$jobname.'"';
      }
      $limit  = 1000;
      $offset = 0;
      $retval = array();
      while (true) {
        $result = $bsock->send_command($cmd.' limit='.$limit.' offset='.$offset, 2, null);
        if (preg_match('/Failed to send result as json. Maybe result message to long?/', $result)) {
          $error = \Zend\Json\Json::decode($result, \Zend\Json\Json::TYPE_ARRAY);

          return $error['result']['error'];
        } else {
          $jobs = \Zend\Json\Json::decode($result, \Zend\Json\Json::TYPE_ARRAY);
          if (empty($result)) {
            return false;
          }
          if (empty($jobs['result']['jobs']) && $jobs['result']['meta']['range']['filtered'] === 0) {
            return array_reverse($retval);
          } else {
            $retval = array_merge($retval, $jobs['result']['jobs']);
          }
        }
        $offset = $offset + $limit;
      }
    } else {
      throw new \Exception('Missing argument.');
    }
  }

  private function generateListJobsJobstatusCommandObjects($requestObject)
  {
    $requestObjects = array();
    if (($requestObject->method == "list" || $requestObject->method == "llist")
      && $requestObject->params->subcommand == "jobs"
      && isset($requestObject->params->jobstatus)
    ) {

      foreach (str_split($requestObject->params->jobstatus) as $jobstatus) {
        $object                           = unserialize(serialize($requestObject));
        $object->params->jobstatus = $jobstatus;
        array_push($requestObjects, $object);
      }
    }

    return $requestObjects;
  }

}
