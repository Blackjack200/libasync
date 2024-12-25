<?php

namespace libasync\runtime;

use Closure;
use Composer\Autoload\ClassLoader;
use Exception;
use libasync\await\Await;
use libasync\utils\ClosureUtils;
use Opis\Closure\SerializableClosure;
use pocketmine\Server;
use pocketmine\utils\Utils;
use RuntimeException;
use function libasync\async;

class ProcessAsyncRuntime implements AsyncRuntime {
	private string $autoloaderData;

	public function runAsync(Closure $closure, ?AsyncExecutionEnvironment $env = null) : AsyncExecutionReceipt {
		try {
			ClosureUtils::validateThreadSafety($closure);
		} catch (Exception $e) {
			throw new RuntimeException("Invalid closure: " . $e->getMessage());
		}

		$rec = new AsyncExecutionReceipt();
		$rec->setCallTrace(Utils::printableCurrentTrace());

		try {
			ob_start();
			$serializedClosure = igbinary_serialize(new SerializableClosure($closure));
			$envData = $env ? igbinary_serialize([new SerializableClosure($env->argsCtor), new SerializableClosure($env->argsDtor)]) : null;
			ob_end_clean();
		} catch (Exception $e) {
			throw new RuntimeException("Failed to serialize closure or environment data: " . $e->getMessage());
		}

		$process = Utils::assumeNotFalse(proc_open(
			[
				PHP_BINARY,
				__DIR__ . '/process.php',
				$this->getAutoloaderData(),
				base64_encode($serializedClosure), base64_encode($envData),
			],
			[
				1 => ['socket'],
				2 => fopen("php://stderr", "wb"),
			],
			$pipes
		), "Something has gone horribly wrong");

		if (!is_resource($process)) {
			throw new RuntimeException("Failed to start subprocess.");
		}

		$stdin = $pipes[1];
		stream_set_blocking($stdin, 0);
		async(static function() use ($process, $rec, $stdin) {
			$output = yield from Await::stream($stdin);
			try {
				[$success, $result] = igbinary_unserialize($output);
				if ($success) {
					$rec->setResult($result);
				} else {
					$rec->setError($result);
				}
			} catch (Exception $e) {
				throw new RuntimeException("Error while processing subprocess output: " . $e->getMessage());
			} finally {
				if (is_resource($stdin)) {
					fclose($stdin);
				}
				if (is_resource($process)) {
					proc_close($process);
				}
			}
		})->panic();

		return $rec;
	}

	private function getAutoloaderData() : string {
		if (!isset($this->autoloaderData)) {
			$loader = Server::getInstance()->getLoader();
			$lookupData = (fn() => $this->fallbackLookup)->call($loader);
			$psrData = (fn() => $this->psr4Lookup)->call($loader);
			$composer = [];
			foreach (ClassLoader::getRegisteredLoaders() as $loader) {
				$vendor = (fn() => $this->vendorDir)->call($loader);
				$composer[] = ($vendor . '/autoload.php');
			}
			$this->autoloaderData = base64_encode(json_encode([$lookupData, $psrData, $composer], JSON_THROW_ON_ERROR));
		}
		return $this->autoloaderData;
	}
}
