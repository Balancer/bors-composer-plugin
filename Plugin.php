<?php

namespace B2\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
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

	public static function postAutoloadDump(Event $event)
	{
		$composer = $event->getComposer();
		$io = $event->getIO();
		$locker = $composer->getLocker();

		$io->write('<info>BORS© update process</info>');

		$lock_data = $locker->getLockData();
		$all_packages = isset($lock_data['packages']) ? $lock_data['packages'] : [];

		$all_packages[] = json_decode(file_get_contents(COMPOSER_ROOT.'/composer.json'), true);

		// Проверяем на наличие autoload.php, а то можем грузиться из .phar
		if(file_exists($d = __DIR__.'/../../autoload.php'))
			// Иначе новые классы не хотят грузиться :-/
			require $d;

		foreach($all_packages as $package)
		{
			$extra = isset($package['extra']) ? $package['extra'] : array();

			if(!empty($package['name']))
			{
				$package_path = COMPOSER_ROOT. '/vendor/' . $package['name'];
				// So detecting root package, not in vendor dir.
				if(!file_exists($package_path.'/composer.json'))
					$package_path = COMPOSER_ROOT;
			}
			else
				$package_path = COMPOSER_ROOT;

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

			self::append_extra($package_path, $extra, 'classes');
			self::append_extra($package_path, $extra, 'route-maps');
			self::append_extra($package_path, $extra, 'templates');
			self::append_extra($package_path, $extra, 'smarty-plugins');
			self::append_extra($package_path, $extra, 'autoroute-prefixes', false);

			if(!empty($extra['bors-app']))
			{
				\B2\Composer\Cache::appendData('config/packages/apps',  [ $package['name'] => $extra['bors-app']]);
				\B2\Composer\Cache::appendData('config/packages/path', [ $package['name'] => $package_path]);
				\B2\Composer\Cache::appendData('config/packages/names', [ $extra['bors-app'] => $package['name']]);
				\B2\Composer\Cache::appendData('config/packages/app-path', [ $extra['bors-app'] => $package_path]);

				if(!empty($extra['bors-routers']))
					\B2\Composer\Cache::appendData('config/apps/routers',  [ $extra['bors-app'] => $extra['bors-routers']]);
			}
		}

		$code = "if(!defined('COMPOSER_ROOT'))\n\tdefine('COMPOSER_ROOT', dirname(dirname(__DIR__)));\n\n";

		$code .= "bors::\$composer_class_dirs = [\n\t".join(",\n\t", array_unique(\B2\Composer\Cache::getData('config/dirs/classes', [])))."];\n";
		$code .= "bors::\$composer_template_dirs = [\n\t".join(",\n\t", array_unique(\B2\Composer\Cache::getData('config/dirs/templates', [])))."];\n";
		$code .= "bors::\$composer_smarty_plugin_dirs = [\n\t".join(",\n\t", array_unique(\B2\Composer\Cache::getData('config/dirs/smarty-plugins', [])))."];\n";
		$code .= "bors::\$composer_route_maps = [\n\t".join(",\n\t", array_unique(\B2\Composer\Cache::getData('config/dirs/route-maps', [])))."];\n";
		$code .= "bors::\$composer_autoroute_prefixes = [\n\t".join(",\n\t", array_unique(\B2\Composer\Cache::getData('config/dirs/autoroute-prefixes', [])))."];\n";

		\B2\Composer\Cache::addAutoload('config/dirs', $code);

		$code = "if(!defined('COMPOSER_ROOT'))\n\tdefine('COMPOSER_ROOT', dirname(dirname(__DIR__)));\n\n";

		$code .= "bors::\$package_apps = [\n";
		foreach(\B2\Composer\Cache::getData('config/packages/apps', []) as $pkg => $app)
			$code .= "\t'$pkg' => '".addslashes($app)."',\n";
		$code .= "];\n";

		$code .= "\nbors::\$package_path = [\n";
		foreach(\B2\Composer\Cache::getData('config/packages/path', []) as $pkg => $path)
			$code .= "\t'$pkg' => ".self::make_path($path).",\n";
		$code .= "];\n";

		\B2\Composer\Cache::addAutoload('config/packages', $code);

		$code = "if(!defined('COMPOSER_ROOT'))\n\tdefine('COMPOSER_ROOT', dirname(dirname(__DIR__)));\n\n";

		$code .= "bors::\$app_routers = [\n";
		foreach(\B2\Composer\Cache::getData('config/apps/routers', []) as $pkg => $routers)
			$code .= "\t'$pkg' => ".var_export($routers, true).",\n";
		$code .= "];\n";

		$code .= "\nbors::\$package_names = [\n";
		foreach(\B2\Composer\Cache::getData('config/packages/names', []) as $app => $pkg)
			$code .= "\t'".addslashes($app)."' => '".addslashes($pkg)."',\n";
		$code .= "];\n";

		$code .= "\nbors::\$package_app_path = [\n";
		foreach(\B2\Composer\Cache::getData('config/packages/app-path', []) as $app => $path)
			$code .= "\t'".addslashes($app)."' => ".self::make_path($path).",\n";
		$code .= "];\n";

		\B2\Composer\Cache::addAutoload('config/apps', $code);
	}

	static function make_path($path)
	{
		$path = str_replace(dirname(dirname(dirname(__DIR__))), '', $path);
		return $path ? "COMPOSER_ROOT.'".addslashes($path)."'" : 'COMPOSER_ROOT';
	}

	static function append_extra($package_path, $extra, $name, $with_path = true)
	{
		$package_path = str_replace(dirname(dirname(dirname(__DIR__))), '', $package_path);

		if(empty($extra['bors-'.$name]))
			return;

		$dirs = $extra['bors-'.$name];

		if($with_path)
		{
			if(is_array($dirs))
			{
				foreach($dirs as $x)
					\B2\Composer\Cache::appendData('config/dirs/'.$name, "COMPOSER_ROOT.'$package_path/$x'");
			}
			else
				\B2\Composer\Cache::appendData('config/dirs/'.$name, "COMPOSER_ROOT.'$package_path/$dirs'");
		}
		else
		{
			if(is_array($dirs))
			{
				foreach($dirs as $x)
					\B2\Composer\Cache::appendData('config/dirs/'.$name, "'$x'");
			}
			else
				\B2\Composer\Cache::appendData('config/dirs/'.$name, "'$dirs'");
		}
	}
}
