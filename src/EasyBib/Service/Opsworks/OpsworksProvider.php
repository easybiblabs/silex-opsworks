<?php
namespace EasyBib\Service\Opsworks;

use Silex\Application;
use Silex\ServiceProviderInterface;

/**
 * Class OpsworksProvider
 * @package EasyBib\Service\Opsworks
 */
class OpsworksProvider implements ServiceProviderInterface
{

    /**
     * @param Application $app
     */
    public function register(Application $app)
    {
        $app['opsworks'] = $app->share(
            function () use ($app) {
                return new Opsworks($app['aws']->get('opsworks'), $app['logger']);
            }
        );
    }

    public function boot(Application $app)
    {
    }
}
