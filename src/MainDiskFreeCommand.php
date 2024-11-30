<?php

namespace rdx\maindiskfree;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;

class MainDiskFreeCommand extends SingleCommandApplication {

	protected function configure() {
		$this->setName('main-disk-free');
		$this->addArgument('moredirs', InputArgument::IS_ARRAY, "Any directories to track size with `du`");
	}

	protected function execute(InputInterface $input, OutputInterface $output) : int {
		// echo "eeh\n";
		// return 0;

		$osUser = posix_getpwuid(posix_geteuid());
		$home = rtrim($osUser['dir'], '\\/');

		$moreDirs = $input->getArgument('moredirs');

		$output = `df -h`;
		if (!preg_match('#(\d+)% +/\s#', "$output ", $match)) {
			echo trim($output) . "\n";
			exit(1);
		}

		$curWhen = date('Y-m-d');
		$curMainPct = (int) $match[1];
		$curMores = array_map(function($dir) {
			$output = `du -s $dir 2>/dev/null`;
			$bytes = (int) trim($output);
			return ceil($bytes / 1024);
		}, $moreDirs);

		if (file_exists($file = "$home/.main-disk-free")) {
			$history = json_decode(trim(file_get_contents($file)), true) ?: [];
			$history = array_map(fn($el) => (array) $el, $history);

			$prevWhen = max(array_keys($history));
			$prevMainPct = $history[$prevWhen][0];
			$prevMores = array_slice($history[$prevWhen], 1);
		}
		else {
			$prevWhen = '-';
			$prevMainPct = 0;
			$prevMores = [];
		}

		$notify = $curMainPct != $prevMainPct;
		if ($notify || $curMores != $prevMores) {
			$history[$curWhen] = [$curMainPct, ...$curMores];
			file_put_contents($file, json_encode($history) . "\n");

			if ($notify) {
				echo "$curMainPct%\n";
				if ($prevMainPct) {
					echo "\n";
					echo "$prevMainPct% ($prevWhen)\n";
				}
			}
		}

		return 0;
	}

}
