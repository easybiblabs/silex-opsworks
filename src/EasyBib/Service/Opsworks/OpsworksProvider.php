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
