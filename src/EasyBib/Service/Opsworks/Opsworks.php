<?php
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
    /** @var OpsWorksClient $opsworks  */
    protected $opsworks;
    /** @var \Monolog\Logger $logger */
    protected $logger;

    /**
     * @param OpsWorksClient $opsworks
     * @param \Monolog\Logger $logger
     */
    public function __construct(OpsWorksClient $opsworks, \Monolog\Logger $logger = null)
    {
        $this->logger = $logger;
        $this->opsworks = $opsworks;
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
     * @param $stackId Opsworks Stack Id
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

    public function getAllStacks()
    {
        $stackIds = array();
        $stacks = $this->opsworks->describeStacks()->getAll();

        foreach ($stacks['Stacks'] as $stack) {
           $stackIds[$stack['StackId']] = $stack['Name'];
        }
        return $stackIds;
        
    }

    private function debug($string)
    {
        if ($this->logger) {
            $this->logger->addDebug($string);
        }
    }
}
