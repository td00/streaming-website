<?php

namespace C3VOC\StreamingWebsite\Command;

use DateTime;
use DateInterval;
use C3VOC\StreamingWebsite\Model\Conferences;

class Download extends AbstractCommand
{
	public function run($argv)
	{
		$conf = $GLOBALS['CONFIG']['DOWNLOAD'];

		if(isset($conf['REQUIRE_USER']))
		{
			if(get_current_user() != $conf['require-user'])
			{
				$this->stderr(
					'Not downloading files for user %s, run this script as user %s',
					get_current_user(),
					$conf['require-user']
				);
				return 2;
			}
		}

		$conferences = Conferences::getConferences();

		if(isset($conf['MAX_CONFERENCE_AGE']))
		{
			$months = intval($conf['MAX_CONFERENCE_AGE']);
			$conferencesAfter = new DateTime();
			$conferencesAfter->sub(new DateInterval('P'.$months.'D'));

			$this->stdout('Skipping Conferences before %s', $conferencesAfter->format('Y-m-d'));
			$conferences = array_filter($conferences, function($conference) use ($conferencesAfter) {
				if($conference->isOpen())
				{
					$this->stdout(
						'  %s: %s',
						'---open---',
						$conference->getSlug()
					);

					return true;
				}

				$isBefore = $conference->endsAt() < $conferencesAfter;

				if($isBefore) {
					$this->stdout(
						'  %s: %s',
						$conference->endsAt()->format('Y-m-d'),
						$conference->getSlug()
					);
				}

				return !$isBefore;
			});
		}

		foreach ($conferences as $conference)
		{
			$this->stdout('');
			$this->stdout('== %s ==', $conference->getSlug());

			$relive = $conference->getRelive();
			if($relive->isEnabled())
			{
				$this->downloadForConference(
					'relive-json',
					$conference,
					$relive->getJsonUrl(),
					$relive->getJsonCache()
				);
			}

			$schedule = $conference->getSchedule();
			if($schedule->isEnabled())
			{
				$this->downloadForConference(
					'schedule-xml',
					$conference,
					$schedule->getScheduleUrl(),
					$schedule->getScheduleCache()
				);
			}

			foreach($conference->getExtraFiles() as $filename => $url)
			{
				$this->downloadForConference(
					'extra-file',
					$conference,
					$url,
					$this->getFileCache($conference, $filename)
				);
			}
		}

		$this->stdout('');
		$this->stdout('== eventkalender ==');
		$this->download(
			'eventkalender',
			'https://c3voc.de/eventkalender/events.json?filter=upcoming&streaming=yes',
			joinpath([$GLOBALS['BASEDIR'], 'configs/upcoming.json'])
		);

		return 0;
	}





	private function getFileCache($conference, $filename)
	{
		return joinpath([$GLOBALS['BASEDIR'], 'configs/conferences', $conference->getSlug(), $filename]);
	}

	private function downloadForConference($what, $conference, $url, $cache)
	{
		$info = parse_url($url);
		if(!isset($info['scheme']) || !isset($info['host']))
		{
			$this->stderr(
				'  !! %s url for conference %s does look like an old-style path: "%s". please update to a full http/https url',
				$what,
				$conference->getSlug(),
				$url
			);
			return false;
		}

		$this->stdout(
			'  downloading %s from %s to %s',
			$what,
			$url,
			$cache
		);
		$resp = $this->doDownload($url, $cache);
		if($resp !== true)
		{
			$this->stderr(
				'  !! download %s for conference %s from %s to %s failed miserably: %s !!',
				$what,
				$conference->getSlug(),
				$url,
				$cache,
				$resp
			);
		}
		return true;
	}

	private function download($what, $url, $cache)
	{
		$this->stdout(
			'  downloading %s from %s to %s',
			$what,
			$url,
			$cache
		);
		$resp = $this->doDownload($url, $cache);
		if($resp !== true)
		{
			$this->stderr(
				'  !! download %s from %s to %s failed miserably: %s !!',
				$what,
				$url,
				$cache,
				$resp
			);
		}
		return true;
	}

	private function doDownload($url, $cache)
	{
		$handle = curl_init($url);
		curl_setopt_array($handle, [
			CURLOPT_FOLLOWLOCATION  => true,
			CURLOPT_MAXREDIRS       => 10,
			CURLOPT_RETURNTRANSFER  => true,
			CURLOPT_SSL_VERIFYPEER  => false, /* accept all certificates, even self-signed */
			CURLOPT_SSL_VERIFYHOST  => 2,     /* verify hostname is in cert */
			CURLOPT_CONNECTTIMEOUT  => 3,     /* connect-timeout in seconds */
			CURLOPT_TIMEOUT         => 5,     /* transfer timeout im seconds */
			CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_USERAGENT       => '@c3voc Streaming-Website Downloader-Cronjob, Contact voc AT c3voc DOT de in case of problems. Might the Winkekatze be with you',
		]);

		$return = curl_exec($handle);
		$info = curl_getinfo($handle);
		curl_close($handle);

		if($info['http_code'] != 200)
			return 'http-code = '.$info['http_code'];

		$tempfile = tempnam(dirname($cache), 'dl-');
		if(!$tempfile)
			return 'could not create tempfile in '.dirname($cache);

		if(false === file_put_contents($tempfile, $return))
			return 'could write data into tempfile '.$tempfile;

		chmod($tempfile, 0644);
		rename($tempfile, $cache);

		return true;
	}
}