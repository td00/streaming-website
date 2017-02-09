<?php

namespace C3VOC\StreamingWebsite\View;

use C3VOC\StreamingWebsite\Model\Conferences;

class StreamsJsonV1 extends View
{
	public function __construct()
	{
		$this->setHeader('Content-Type', 'application/json');
	}

	public function render()
	{
		$struct = [];

		foreach (Conferences::getActiveConferences() as $conference)
		{
			$overview = $conference->getOverview();

			foreach($overview->getGroups() as $group => $rooms)
			{
				$roomstruct = array();
				/** @var Room[] $rooms */
				foreach($rooms as $room)
				{
					$streams = array();
					foreach($room->getStreams() as $stream)
					{
						$key = $stream->getSelection().'-'.$stream->getLanguage();

						$urls = array();
						switch($stream->getPlayerType())
						{
							case 'video':
								foreach ($stream->getVideoProtos() as $proto => $display)
								{
									$urls[$proto] = array(
										'display' => $display,
										'tech' => $stream->getVideoTech($proto),
										'url' => $stream->getVideoUrl($proto),
									);
								}
								break;

							case 'slides':
								foreach ($stream->getSlidesProtos() as $proto => $display)
								{
									$urls[$proto] = array(
										'display' => $display,
										'tech' => $stream->getSlidesTech($proto),
										'url' => $stream->getSlidesUrl($proto),
									);
								}
								break;

							case 'audio':
								foreach ($stream->getAudioProtos() as $proto => $display)
								{
									$urls[$proto] = array(
										'display' => $display,
										'tech' => $stream->getAudioTech($proto),
										'url' => $stream->getAudioUrl($proto),
									);
								}
								break;

							case 'music':
								foreach ($stream->getMusicProtos() as $proto => $display)
								{
									$urls[$proto] = array(
										'display' => $display,
										'tech' => $stream->getMusicTech($proto),
										'url' => $stream->getMusicUrl($proto),
									);
								}
								break;

							case 'dash':
								$urls['dash'] = array(
									'display' => 'DASH',
									'tech' => $room->getDashTech(),
									'url' => $room->getDashManifestUrl(),
								);
								break;
						}

						$streams[] = array(
							'slug' => $key,
							'display' => $stream->getDisplay(),
							'type' => $stream->getPlayerType(),
							'isTranslated' => $stream->isTranslated(),
							'videoSize' => $stream->getVideoSize(),
							'urls' => (object)$urls,
						);
					}

					$roomstruct[] = array(
						'slug' => $room->getSlug(),
						'schedulename' => $room->getScheduleName(),
						'thumb' => forceslash(baseurl()).$room->getThumb(),
						'link' => forceslash(baseurl()).$room->getLink(),
						'display' => $room->getDisplay(),
						'streams' => $streams,
					);
				}

				$struct[] = array(
					'conference' => $conference->getTitle(),
					'group' => $group,
					'rooms' => $roomstruct,
				);
			}
		}

		return json_encode($struct, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
}
