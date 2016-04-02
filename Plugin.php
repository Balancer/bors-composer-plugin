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
			define('COMPOSER_ROOT', dirname(dirname(dirname(__DIR__))));
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

		// Проверяем на наличие autoload.php, а то можем грузиться из .phar
		if(file_exists($d = __DIR__.'/../../autoload.php'))
			// Иначе новые классы не хотят грузиться :-/
			require $d;

		foreach($all_packages as $package)
		{
			$extra = isset($package['extra']) ? $package['extra'] : array();

			if(isset($extra['bors-calls']))
			{
				foreach($extra['bors-calls'] as $callback => $data)
				{
					if(is_callable($callback))
						call_user_func($callback, $data, $composer, $package);
					else
						$io->write('<error>Incorrect callback: '.print_r($callback, true).'</error>');
				}
			}

			if(!empty($extra['bors-classes']))
			{
				$class_path = COMPOSER_ROOT. '/vendor/' . $package['name'] . '/' . $extra['bors-classes'];
				\B2\Composer\Cache::appendData('config/dirs/classes', "'$class_path'");
//				$io->write('<info>Bors classes in: '.print_r($package, true).'</info>');
//				$io->write("<info>Bors classes in: {$class_path}</info>");
			}

			if(!empty($extra['bors-templates']))
			{
				$class_path = COMPOSER_ROOT. '/vendor/' . $package['name'] . '/' . $extra['bors-templates'];
				\B2\Composer\Cache::appendData('config/dirs/templates', "'$class_path'");
			}

			if(!empty($extra['bors-smarty-plugins']))
			{
				$class_path = COMPOSER_ROOT. '/vendor/' . $package['name'] . '/' . $extra['bors-templates'];
				\B2\Composer\Cache::appendData('config/dirs/smarty-plugins', "'$class_path'");
			}
		}

		$code = "bors::\$composer_class_dirs = array(".join(', ', array_unique(\B2\Composer\Cache::getData('config/dirs/classes'))).");\n";
		$code .= "bors::\$composer_template_dirs = array(".join(', ', array_unique(\B2\Composer\Cache::getData('config/dirs/templates'))).");\n";
		$code .= "bors::\$composer_smarty_plugin_dirs = array(".join(', ', array_unique(\B2\Composer\Cache::getData('config/dirs/smarty-plugins'))).");\n";

		\B2\Composer\Cache::addAutoload('config/dirs', $code);
	}
}
