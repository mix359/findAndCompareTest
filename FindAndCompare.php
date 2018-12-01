<?php

class FindAndCompare {

	/**
	 * Execute the find and compare
	 *
	 * @param string $firstUrl
	 * @param string $secondUrl
	 * @throws Exception
	 */
	public function execute($firstUrl, $secondUrl) {
		$this->log("start execute");
		$firstPageUrls = $this->retrieveAllUrlsFromFirstDepthOfPage($firstUrl);
		$secondPageUrls = $this->retrieveAllUrlsFromFirstDepthOfPage($secondUrl);

		$this->log("looking for similar urls");
		$mostSimilarUrls = [];
		foreach ($firstPageUrls as $firstPageUrl) {
			list($bestSecondPageUrl, $perc) = $this->findMostSimilarUrlForUrl($firstPageUrl, $secondPageUrls);
			$mostSimilarUrls[] = ['firstPageUrl' => $firstPageUrl, 'secondPageUrl' => $bestSecondPageUrl, 'percent' => round($perc)];
			unset($bestSecondPageUrl, $perc);
		}

		$this->log("creating the csv");
		$filename = "url_compare_";
		$filename .= str_replace(['http://', 'https://', '.', "/"], ['', '', '_', '_'],$firstUrl)."_";
		$filename .= str_replace(['http://', 'https://', '.', "/"], ['', '', '_', '_'],$secondUrl);
		$this->createAndDownloadCsvFromArray($mostSimilarUrls, $filename);

		$this->log("end execute");
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
	 * Retrieve all the urls present in the first depth pages of the provided url
	 *
	 * @param string $url
	 * @return array
	 * @throws Exception
	 */
	protected function retrieveAllUrlsFromFirstDepthOfPage($url) {
		if($url == "" || !filter_var($url, FILTER_VALIDATE_URL)) {
			throw new \Exception("URL {$url} inviato non valido");
		}

		$this->log("retrive {$url}");
		$html = $this->loadHTMLData($url);
		$this->log("-find canonical");
		$homePageUrls = [];
		$canonicalUrl = $this->findCanonicalUrlOfHtml($html, $url);
		$this->log("-find links");
		$this->findAllAnchorUrlsInHtml($html, $canonicalUrl, $homePageUrls);
		unset($html);

		$this->log("-loading and searching in ".count($homePageUrls)." urls");
		$homeAndFirstDepthUrls = $homePageUrls;
		foreach ($homePageUrls as $homePageUrl) {
			try {
				$html = $this->loadHTMLData($homePageUrl);
				$this->findAllAnchorUrlsInHtml($html, $canonicalUrl, $homeAndFirstDepthUrls);
			} catch (\Exception $e) {}
			unset($html);
		}

		$this->log("-found ".count($homeAndFirstDepthUrls)." urls");

		return $homeAndFirstDepthUrls;
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
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
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
	 * Log a message into the debug file
	 *
	 * @param $message
	 * @param bool $includeTime
	 */
	protected function log($message, $includeTime = TRUE) {
		if(!defined("DEBUG_FILE") || DEBUG_FILE === "") {
			return;
		}

		file_put_contents(DEBUG_FILE, ($includeTime?date("[H:i:s] "):"").$message.PHP_EOL, FILE_APPEND);
	}

}