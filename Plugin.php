<?php

namespace B2\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\CommandEvent;
use Composer\Script\ScriptEvents;

class Plugin implements PluginInterface, EventSubscriberInterface
{
	public function activate(Composer $composer, IOInterface $io)
	{
		$this->composer = $composer;
		$this->io = $io;

		if(!defined('COMPOSER_ROOT'))
			// Ад. Но сходу не нашёл, как получить baseDir в Composer.
			define('COMPOSER_ROOT', dirname(dirname(dirname(dirname(dirname(__DIR__))))));
	}

	public static function getSubscribedEvents()
	{
		return array(
			ScriptEvents::POST_AUTOLOAD_DUMP => array(
				array('postAutoloadDump', 0), // Приоритет, чем выше, тем раньше вызывается.
			),
		);
	}

	public static function postAutoloadDump(CommandEvent $event)
	{
		$composer = $event->getComposer();
		$io = $event->getIO();
		$locker = $composer->getLocker();

		$io->write('<info>BORS© update process</info>');

		$lock_data = $locker->getLockData();
		$all_packages = isset($lock_data['packages']) ? $lock_data['packages'] : array();

//		\B2\Router\Fastroute::adds(['qqq www eee']);

		foreach($all_packages as $package)
		{
			$extra = isset($package['extra']) ? $package['extra'] : array();
			if(isset($extra['bors-calls']))
			{
				foreach($extra['bors-calls'] as $callback => $data)
				{
					if(preg_match('/^(.+)::(\w+)$/', $callback, $m))
						$callback = array("\\".$m[1], $m[2]);

//					if(is_callable($callback))
						call_user_func($callback, $data, $composer, $package);
//					else
//						$io->write('<error>Incorrect callback: '.print_r($callback, true).'</error>');
				}
			}
		}
	}
}
