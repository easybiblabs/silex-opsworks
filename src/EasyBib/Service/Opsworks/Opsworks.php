<?php
/*
 * This file is part of easybib/opswork-provider
 *
 * (c) Imagine Easy Solutions, LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author   Florian Holzhauer <fh-opsworks@fholzhauer.de>
 * @license  BSD-2-Clause
 * @link     http://www.imagineeasy.com
 */

namespace EasyBib\Service\Opsworks;

use Aws\Common\Credentials\CredentialsInterface;
use Aws\OpsWorks\OpsWorksClient;

/**
 * Class OpsWorks
 * @package EasyBib\Service\Opsworks
 *
 * The Opsworks Service Provider - encapsulates the AWS Opsworks class
 *
 */
class Opsworks
{
    /** @var OpsWorksClient $opsworks */
    protected $opsworks;
    /** @var \Monolog\Logger $logger */
    protected $logger;
    /** @var \Doctrine\Common\Cache\Cache */
    protected $cache;

    /**
     * @param OpsWorksClient $opsworks
     * @param \Doctrine\Common\Cache\Cache $cache
     * @param \Monolog\Logger $logger
     */
    public function __construct(
        OpsWorksClient $opsworks,
        \Doctrine\Common\Cache\Cache $cache = null,
        \Monolog\Logger $logger = null
    ) {
        $this->logger = $logger;
        $this->opsworks = $opsworks;
        if ($cache) {
            $this->cache = $cache;
        } else {
            $this->cache = new \Doctrine\Common\Cache\ArrayCache();
        }
        $this->cache = array();

    }

    /**
     * @param CredentialsInterface $credentials
     * @return mixed
     */
    public function setCredentials(CredentialsInterface $credentials)
    {
        $this->debug('opsworks::setCredentials');
        return $this->opsworks->setCredentials($credentials);
    }

    /**
     * @param string $appid Opsworks Application Id
     * @param string $stackid Opsworks Stack Id
     * @param array $instanceids Opsworks Instance Ids
     * @param string $comment optional Deploy Comment
     * @param string $customJson optional Custom Json for the deploy
     * @return mixed
     */
    public function deployApp($appid, $stackid, $instanceids, $comment = 'Continuous Deployment', $customJson = '')
    {
        $deployParameters = array(
            'AppId' => $appid,
            'Command' => array(
                'Name' => 'deploy'
            ),
            'Comment' => $comment,
            'CustomJson' => $customJson,
            'StackId' => $stackid,
            'InstanceIds' => $instanceids

        );
        $this->debug('opsworks::createDeployment');
        $result = $this->opsworks->createDeployment($deployParameters);
        $deploymentId = $result->get('DeploymentId');
        return $deploymentId;
    }

    public function executeStackCommand($stackId, $command, $args = null)
    {
        $deployParameters = array(
            'Command' => array(
                'Name' => $command
            ),
            'StackId' => $stackId,
            'InstanceIds' => $this->getInstanceIdsForStack($stackId)
        );

        if ($args) {
            $deployParameters['Command']['Args'] = $args;
        }

        $result = $this->opsworks->createDeployment($deployParameters);
        $deploymentId = $result->get('DeploymentId');
        return $deploymentId;
    }

    /**
     * @param string $appid Opsworks Application Id
     * @param string $revision Git Source Revision
     * @return mixed
     */
    public function updateAppRevision($appid, $revision)
    {
        $appParameters = $this->getAppParameters($appid);
        $appParameters['AppSource']['Revision'] = $revision;
        $this->debug('opsworks::updateApp');
        return $this->opsworks->updateApp($appParameters);
    }

    /**
     *
     * Returns the Application Parameters from Opsworks
     * Strips Sshkey and SslConfiguration, since we get it filtered (and therefore useless) from Opsworks anyway
     *
     * @param $appid Opsworks Application Id
     * @return mixed
     * @throws \UnexpectedValueException
     */
    public function getAppParameters($appid)
    {
        $describeParameters = array(
            'AppIds' => array(
                $appid
            )
        );
        $this->debug('opsworks::getAppParameters');
        $result = $this->opsworks->describeApps($describeParameters);

        $apps = $result->get('Apps');

        if (count($apps) != 1) {
            throw new \UnexpectedValueException('Unable to identify app in Opsworks');
        }

        $appParameters = array_pop($apps);
        // sshkey is returned as "*****FILTERED*****" from Opsworks, and we dont want to set this in config :)
        // same for sslconfig
        unset($appParameters['AppSource']['SshKey']);
        unset($appParameters['SslConfiguration']);
        return $appParameters;
    }

    /**
     * @param $appid Opsworks Application Id
     * @return mixed
     * @throws \UnexpectedValueException
     */
    public function getStackIdForApp($appid)
    {
        $describeParameters = array(
            'AppIds' => array(
                $appid
            )
        );
        $result = $this->opsworks->describeApps($describeParameters);

        $apps = $result->get('Apps');

        if (count($apps) != 1) {
            throw new \UnexpectedValueException('Unable to identify app in Opsworks');
        }

        $appParameters = array_pop($apps);

        $this->debug('opsworks::getStackIdForApp - returning ' . $appParameters['StackId']);

        return $appParameters['StackId'];
    }

    /**
     * @param string $stackId stringOpsworks Stack Id
     * @return array
     */
    public function getInstanceIdsForStack($stackId)
    {
        $instanceParameters = $this->opsworks->describeInstances(array('StackId' => $stackId));
        $instances = $instanceParameters->get('Instances');
        $instanceIds = array();
        foreach ($instances as $instance) {
            $instanceIds[] = $instance['InstanceId'];
        }
        $this->debug('opsworks::getInstanceIdsForStack - returning ' . implode(',', $instanceIds));
        return $instanceIds;
    }

    /**
     * Returns all Instance Ids of a layer
     * @param string $layer Opsworks Layer Id
     * @return array
     */
    public function getInstanceIdsForLayer($layerId)
    {
        $instanceParameters = $this->opsworks->describeInstances(array('LayerId' => $layerId));
        $instances = $instanceParameters->get('Instances');
        $instanceIds = array();
        foreach ($instances as $instance) {
            $instanceIds[] = $instance['InstanceId'];
        }
        $this->debug('opsworks::getInstanceIdsForLayer - returning ' . implode(',', $instanceIds));
        return $instanceIds;
    }

    /**
     * Returns all apps belonging to a stack
     * @param string $stackId Opsworks Stack Id
     * @return array
     */
    public function getAllAppsForStack($stackId)
    {
        $opsworksApps = array();
        $apiResult = $this->opsworks->describeApps(array('StackId' => $stackId))->get('Apps');
        foreach ($apiResult as $opsworksApp) {
            $opsworksApps[$opsworksApp['AppId']] = $opsworksApp['Name'];
        }
        return $opsworksApps;
    }

    /**
     * Returns all apps across all stacks, cached
     * @return array
     */
    public function getAllApps()
    {
        if ($this->getCache('get_all_apps')) {
            return $this->getCache('get_all_apps');
        }

        $opsworksApps = array();
        $stackIds = array_keys($this->getAllStacks());
        foreach ($stackIds as $stackId) {
            $stackApps = $this->getAllAppsForStack($stackId);
            $opsworksApps = array_merge($opsworksApps, $stackApps);
        }

        $this->setCache('get_all_apps', $opsworksApps);

        return $opsworksApps;
    }

    /**
     * Returns all stacks, cached
     * @return array
     */
    public function getAllStacks()
    {
        if ($this->getCache('get_all_stacks')) {
            return $this->getCache('get_all_stacks');
        }

        $stackResult = array();
        $stacks = $this->opsworks->describeStacks()->get('Stacks');

        foreach ($stacks as $stack) {
            $stackResult[$stack['StackId']] = $stack;
        }
        $this->setCache('get_all_stacks', $stackResult);

        return $stackResult;

    }

    /**
     * Returns last deployments for a stack
     * @param string $stackId Opsworks Stack Id
     * @return array
     */
    public function getDeploymentsForStack($stackId)
    {
        return $this->opsworks
            ->describeDeployments(
                array('StackId' => $stackId)
            )
            ->get('Deployments');
    }

    /**
     * Returns all recent deployments across all stacks, cached
     * @return array
     */
    public function getAllDeployments()
    {
        if ($this->getCache('get_all_deployments')) {
            return $this->getCache('get_all_deployments');
        }

        $deployments = array();
        $stackIds = array_keys($this->getAllStacks());
        foreach ($stackIds as $stackId) {
            $deployments[$stackId] = $this->getDeploymentsForStack($stackId);
        }

        $this->setCache('get_all_deployments', $deployments);

        return $deployments;
    }

    private function debug($string)
    {
        if ($this->logger) {
            $this->logger->addDebug($string);
        }
    }

    private function setCache($identifier, $value)
    {
        return $this->cache->save('bib-opsstatus-' . $identifier, $value, 60 * 60 * 24);
    }

    private function getCache($identifier)
    {
        return $this->cache->fetch('bib-opsstatus-' . $identifier);
    }
}
