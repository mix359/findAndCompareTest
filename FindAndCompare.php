<?php

class FindAndCompare {

	protected const SECONDS_TIME_LIMIT = 23;
	protected $startTime;
	protected $jobId;
	protected $jobStep = '';
	protected $executedJobStep = '';
	protected $jobStartTime;
	protected $firstUrl;
	protected $secondUrl;
	protected $firstHomePageUrls = [];
	protected $secondHomePageUrls = [];
	protected $firstPageUrls = [];
	protected $secondPageUrls = [];
	protected $firstPageCanonical = "";
	protected $secondPageCanonical = "";
	protected $mostSimilarUrls = [];
	protected $similarityCompareIdx = 0;
	protected $firstHomePageIdx = 0;
	protected $secondHomePageIdx = 0;

	protected const JOB_DATA_KEYS = ['firstUrl', 'secondUrl', 'firstPageUrls', 'secondPageUrls', 'firstHomePageUrls',
									 'secondHomePageUrls', 'firstPageCanonical', 'secondPageCanonical', 'mostSimilarUrls',
									 'similarityCompareIdx', 'firstHomePageIdx', 'secondHomePageIdx'];

	/**
	 * FindAndCompare constructor.
	 * @param $startTime
	 */
	public function __construct() {
		$this->startTime = time();
		if(defined('DEBUG_FILE')) {
			if(file_exists(DEBUG_FILE)) {
				unlink(DEBUG_FILE);
			}
			touch(DEBUG_FILE);
		}
	}

	/**
	 * Start the job
	 *
	 * @param string $firstUrl
	 * @param string $secondUrl
	 * @throws Exception
	 */
	public function startJob($firstUrl, $secondUrl) {
		if(trim($firstUrl) === "" || !filter_var($firstUrl, FILTER_VALIDATE_URL)) {
			throw new \Exception("Il primo url inviato non sembra essere valido");
		}

		if(trim($secondUrl) === "" || !filter_var($secondUrl, FILTER_VALIDATE_URL)) {
			throw new \Exception("Il secondo url inviato non sembra essere valido");
		}

		$this->jobId = uniqid();
		$this->jobStartTime = time();
		$this->executedJobStep = "jobStarting";
		$this->jobStep = "analyzeFirstUrlHome";
		$this->firstUrl = trim($firstUrl);
		$this->secondUrl = trim($secondUrl);
		$this->redirect();
	}

	/**
	 * Load the state of the job and execute it
	 *
	 * @param string $jobId
	 * @throws Exception
	 */
	public function execute($jobId) {
		$this->loadJobState($jobId);

		switch ($this->jobStep) {
			case "analyzeFirstUrlHome":
				$this->executedJobStep = "analyzingFirstUrlHome";
				$this->retrieveUrlsFromHomePage($this->firstUrl, $this->firstHomePageUrls, $this->firstPageCanonical);
				if(empty($this->firstHomePageUrls)) {
					throw new \Exception("Non è stato possibile recuperare alcun link dalla pagina {$this->firstUrl}");
				}

				$this->firstPageUrls = $this->firstHomePageUrls;
				$this->jobStep = "analyzeFirstUrls";
				$this->redirect();
				break;

			case "analyzeFirstUrls":
				$this->executedJobStep = "analyzingFirstUrls";
				$firstHomePageUrlsCount = count($this->firstHomePageUrls);
				while($this->firstHomePageIdx < $firstHomePageUrlsCount) {
					try {
						$html = $this->loadHTMLData($this->firstHomePageUrls[$this->firstHomePageIdx]);
						$this->findAllAnchorUrlsInHtml($html, $this->firstPageCanonical, $this->firstPageUrls);
						unset($html);
					} catch (\Exception $e) {}
					$this->firstHomePageIdx++;

					if(time() > $this->startTime + self::SECONDS_TIME_LIMIT) {
						$this->redirect();
					}
				}
				$this->jobStep = "analyzeSecondUrlHome";
				$this->redirect();
				break;

			case "analyzeSecondUrlHome":
				$this->executedJobStep = "analyzingSecondUrlHome";
				$this->retrieveUrlsFromHomePage($this->secondUrl, $this->secondHomePageUrls, $this->secondPageCanonical);
				if(empty($this->secondHomePageUrls)) {
					throw new \Exception("Non è stato possibile recuperare alcun link dalla pagina {$this->secondUrl}");
				}

				$this->secondPageUrls = $this->secondHomePageUrls;
				$this->jobStep = "analyzeSecondUrls";
				$this->redirect();
				break;

			case "analyzeSecondUrls":
				$this->executedJobStep = "analyzingSecondUrls";
				$secondHomePageUrlsCount = count($this->secondHomePageUrls);
				while($this->secondHomePageIdx < $secondHomePageUrlsCount) {
					try {
						$html = $this->loadHTMLData($this->secondHomePageUrls[$this->secondHomePageIdx]);
						$this->findAllAnchorUrlsInHtml($html, $this->secondPageCanonical, $this->secondPageUrls);
						unset($html);
					} catch (\Exception $e) {}
					$this->secondHomePageIdx++;

					if(time() > $this->startTime + self::SECONDS_TIME_LIMIT) {
						$this->redirect();
					}
				}
				$this->jobStep = "searchSimilarity";
				$this->redirect();
				break;

			case "searchSimilarity":
				$this->executedJobStep = "searchingSimilarity";
				$firstPageUrlsCount = count($this->firstPageUrls);
				while($this->similarityCompareIdx < $firstPageUrlsCount) {
					list($bestSecondPageUrl, $perc) = $this->findMostSimilarUrlForUrl($this->firstPageUrls[$this->similarityCompareIdx], $this->secondPageUrls);
					$this->mostSimilarUrls[] = ['firstPageUrl' => $this->firstPageUrls[$this->similarityCompareIdx], 'secondPageUrl' => $bestSecondPageUrl, 'percent' => round($perc)];
					unset($bestSecondPageUrl, $perc);

					$this->similarityCompareIdx++;
					if(time() > $this->startTime + self::SECONDS_TIME_LIMIT) {
						$this->redirect();
					}
				}

				$this->jobStep = "generateCsv";
				$this->redirect();
				break;

			case "generateCsv":
				$this->log("creating the csv");
				$this->executedJobStep = "generatingCsv";
				$filename = "url_compare_";
				$filename .= str_replace(['http://', 'https://', '.', "/"], ['', '', '_', '_'],$this->firstUrl)."_";
				$filename .= str_replace(['http://', 'https://', '.', "/"], ['', '', '_', '_'],$this->secondUrl);
				$this->createAndDownloadCsvFromArray($this->mostSimilarUrls, $filename);
				unlink("{$jobId}.job");
				break;
		}
	}

	/**
	 * Find the most similar url in a list of urls and his similarity percentage
	 *
	 * @param string $url
	 * @param array $compareUrls
	 * @return array
	 */
	protected function findMostSimilarUrlForUrl($url, &$compareUrls) {
		$bestUrl = null;
		$bestPerc = 0;
		foreach ($compareUrls as $compareUrl) {
			$perc = 0;
			similar_text($url, $compareUrl, $perc);
			if($perc === 100) {
				return [$url, $perc];
			} elseif ($perc > $bestPerc) {
				$bestUrl = $compareUrl;
				$bestPerc = $perc;
			}
		}

		return [$bestUrl, $bestPerc];
	}

	/**
	 * Retrive all the links in the homepage url passed
	 *
	 * @param $url
	 * @param $homePageUrls
	 * @param $canonicalUrl
	 * @throws Exception
	 */
	protected function retrieveUrlsFromHomePage($url, &$homePageUrls, &$canonicalUrl) {
		$this->log("retrive {$url}");
		$html = $this->loadHTMLData($url);
		$homePageUrls = [];
		$canonicalUrl = $this->findCanonicalUrlOfHtml($html, $url);
		$this->log("-found canonical {$canonicalUrl}");
		$this->findAllAnchorUrlsInHtml($html, $canonicalUrl, $homePageUrls);
		$this->log("-found links");
	}

	/**
	 * Find the canonical url of the page in the HTML
	 *
	 * @param $html
	 * @param $url
	 * @return string
	 * @throws Exception
	 */
	protected function findCanonicalUrlOfHtml($html, $url) {
		$domDoc = new DOMDocument();
		if(!@$domDoc->loadHTML($html)) {
			throw new \Exception("Impossibile elaborare l'html della pagina {$url}");
		}

		$xpath = new DOMXpath($domDoc);
		$canonical = $xpath->query("//link[@rel='canonical']");
		if(count($canonical) > 0 && $canonical->item(0)->hasAttribute("href") && $canonical->item(0)->getAttributeNode('href')->value != "") {
			return $canonical->item(0)->getAttributeNode('href')->value;
		}

		$ogUrl = $xpath->query("//meta[@property='og:url']");
		if(count($ogUrl) > 0 && $ogUrl->item(0)->hasAttribute("content") && $ogUrl->item(0)->getAttributeNode('content')->value != "") {
			return $ogUrl->item(0)->getAttributeNode('content')->value;
		}

		return $url;
	}

	/**
	 * Look into the passed Html to find all the urls present in the anchor tags
	 *
	 * @param string $html
	 * @param string $canonicalUrl
	 * @param array $urls
	 * @throws Exception
	 */
	protected function findAllAnchorUrlsInHtml($html, $canonicalUrl, &$urls = []) {
		$domDoc = new DOMDocument();
		if(!@$domDoc->loadHTML($html)) {
			throw new \Exception("Impossibile elaborare l'html della pagina {$canonicalUrl}");
		}

		$homeUrlLength = strlen($canonicalUrl);

		foreach ($domDoc->getElementsByTagName('a') as $anchorTag) {
			foreach ($anchorTag->attributes as $attribute) {
				if($attribute->name === "href") {
					$url = (substr($attribute->value,0,1) === "/" ? $canonicalUrl : "").$attribute->value;

					if(filter_var($url, FILTER_VALIDATE_URL) && substr($url, 0, $homeUrlLength) === $canonicalUrl && !in_array($url, $urls)) {
						$urls[] = $url;
						break;
					}
				}
			}
		}
	}

	/**
	 * Return the html of the provided url
	 *
	 * @param string $url
	 * @return string
	 * @throws Exception
	 */
	protected function loadHTMLData($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		//@todo appear as a browser?
		$html = curl_exec($ch);
		curl_close($ch);

		if($html === FALSE || $html == "") {
			throw new \Exception("Non è stato possibile recuperare i dati per l'url: {$url}");
		}

		return $html;
	}

	/**
	 * Generate and download the csv file based on the given array
	 *
	 * @param array $array
	 * @param string $filename
	 * @throws Exception
	 */
	protected function createAndDownloadCsvFromArray($array, $filename) {
		if(empty($array)) {
			throw new \Exception("Non son stati trovati valori da salvare");
		}

		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="'.$filename.'.csv"');
		$out = fopen('php://output', 'w');

		fputcsv($out, array_keys($array[0]));
		foreach ($array as $row) {
			fputcsv($out, $row);
		}
		fclose($out);
	}

	/**
	 * @param $jobId
	 * @throws Exception
	 */
	protected function loadJobState($jobId) {
		if($jobId === "") {
			throw new \Exception("Id di lavoro non valido.");
		}

		$jobStateSerialized = @file_get_contents("{$jobId}.job");
		if($jobStateSerialized === FALSE) {
			throw new \Exception("Non è stato possibile leggere il file di lavoro");
		}

		$jobState = @unserialize($jobStateSerialized);
		if($jobState === FALSE || !is_array($jobState) || !isset($jobState['step']) || !isset($jobState['startTime']) ) {
			throw new \Exception("File di lavoro non valido");
		}

		$this->jobId = $jobId;
		$this->jobStep = $jobState['step'];
		$this->jobStartTime = $jobState['startTime'];

		foreach (self::JOB_DATA_KEYS as $key) {
			if(!isset($jobState[$key])) {
				throw new \Exception("File di lavoro non valido: chiave mancante {$key}");
			}

			$this->$key = $jobState[$key];
		}
	}

	/**
	 * Save the local variables in the job file
	 *
	 * @throws Exception
	 */
	protected function saveJobState() {
		$jobState = [
			'step' => $this->jobStep,
			'startTime' => $this->jobStartTime,
		];

		foreach (self::JOB_DATA_KEYS as $key) {
			$jobState[$key] = $this->$key;
		}

		if(@file_put_contents("{$this->jobId}.job", serialize($jobState)) === FALSE) {
			throw new \Exception("Non è stato possibile salvare il file di lavoro.");
		}
	}

	/**
	 * Display the current status of the job
	 */
	protected function displayJobState() {
		switch($this->executedJobStep) {
			case "jobStarting":
				$stepMessage = "Inizio del processo {$this->jobId} di analisi degli urls {$this->firstUrl} {$this->secondUrl}";
				break;

			case "analyzingFirstUrlHome":
				$stepMessage = "Analisi del primo URL";
				break;

			case "analyzingFirstUrls":
				$stepMessage = "Analisi degli url di primo livello del primo URL {$this->firstHomePageIdx}/".count($this->firstHomePageUrls);
				break;

			case "analyzingSecondUrlHome":
				$stepMessage = "Analisi del secondo URL";
				break;

			case "analyzingSecondUrls":
				$stepMessage = "Analisi degli url di primo livello del secondo URL {$this->secondHomePageIdx}/".count($this->secondHomePageUrls);
				break;

			case "searchingSimilarity":
				$stepMessage = "Ricerca delle similarità: {$this->similarityCompareIdx}/".count($this->firstPageUrls);
				break;

			case "generatingCsv":
				$stepMessage = "Analisi del primo URL";
				break;

			default:
				$stepMessage = "";
		}

		$this->log($stepMessage);
		$elapsedTime = time() - $this->startTime;
		$totalElapsedTime = time() - $this->jobStartTime;
		$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
		$redirectUrl = $protocol.$_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . "?jobId=".$this->jobId;

		include_once "showJobState.php";
	}

	/**
	 * Save the job and display the status of the job with the redirect
	 *
	 * @throws Exception
	 */
	protected function redirect() {
		$this->saveJobState();
		$this->displayJobState();
		die();
	}


	/**
	 * Log a message into the debug file
	 *
	 * @param $message
	 * @param bool $includeTime
	 */
	protected function log($message, $includeTime = TRUE) {
		if(!defined("DEBUG_FILE") || DEBUG_FILE === "") {
			return;
		}

		@file_put_contents(DEBUG_FILE, ($includeTime?date("[H:i:s] "):"").$message.PHP_EOL, FILE_APPEND);
	}

}