<?php
class FacebookScraper extends BaseScraper {

    private $accessToken;
    private $pageHandle;

    public function __construct(Database $database, $sourceId) {
        parent::__construct($database, $sourceId);

        // Load access token from settings table
        $stmt = $this->db->prepare("SELECT value FROM settings WHERE key = 'facebook_access_token'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->accessToken = $row ? $row['value'] : null;

        // Extract page handle from source URL (e.g. https://www.facebook.com/aTabernadoPeter/events)
        $stmt = $this->db->prepare("SELECT url FROM sources WHERE id = ?");
        $stmt->execute([$sourceId]);
        $source = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($source && preg_match('/facebook\.com\/([^\/\?]+)/', $source['url'], $m)) {
            $this->pageHandle = $m[1];
        }
    }

    public function scrape() {
        if (!$this->accessToken) {
            return ['error' => 'Facebook access token não configurado. Configura em Admin → Facebook API.'];
        }

        if (!$this->pageHandle) {
            return ['error' => 'Não foi possível determinar o handle da página Facebook a partir do URL da fonte.'];
        }

        $eventsScraped = 0;
        $errors        = [];

        try {
            $url = 'https://graph.facebook.com/v22.0/' . urlencode($this->pageHandle) . '/events'
                 . '?fields=id,name,description,start_time,end_time,place,cover'
                 . '&limit=50'
                 . '&time_filter=upcoming'
                 . '&access_token=' . urlencode($this->accessToken);

            $response = $this->fetchUrl($url);
            if (!$response) {
                return ['error' => 'Falha ao comunicar com a API do Facebook.'];
            }

            $data = json_decode($response, true);
            if (isset($data['error'])) {
                return ['error' => $data['error']['message']];
            }

            $fbEvents = $data['data'] ?? [];

            foreach ($fbEvents as $fbEvent) {
                try {
                    $event = $this->parseEvent($fbEvent);
                    if ($event && $this->saveEvent(
                        $event['title'],
                        $event['description'],
                        $event['date'],
                        $event['category'],
                        $event['image'],
                        $event['url'],
                        $event['location'],
                        $event['start_date'],
                        $event['end_date']
                    )) {
                        $eventsScraped++;
                    }
                } catch (Exception $e) {
                    $errors[] = 'Evento "' . ($fbEvent['name'] ?? '?') . '": ' . $e->getMessage();
                }
            }

            $this->updateLastScraped();

            return [
                'events_scraped' => $eventsScraped,
                'errors'         => $errors,
            ];

        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function parseEvent($fbEvent) {
        $title     = $fbEvent['name']       ?? null;
        $startTime = $fbEvent['start_time'] ?? null;

        if (!$title || !$startTime) return null;

        $startDate = $this->parseDate($startTime);
        if (!$startDate) return null;

        $endDate = isset($fbEvent['end_time']) ? $this->parseDate($fbEvent['end_time']) : null;

        // Description
        $description = trim($fbEvent['description'] ?? '');
        if (strlen($description) > 500) {
            $description = substr($description, 0, 500) . '...';
        }

        // Location — prefer place name from the event, fall back to source page name
        $location = 'Taberna do Peter';
        if (!empty($fbEvent['place']['name'])) {
            $location = $fbEvent['place']['name'];
        }

        // Cover image
        $image = $fbEvent['cover']['source'] ?? null;

        // Canonical Facebook event URL
        $url = 'https://www.facebook.com/events/' . $fbEvent['id'];

        return [
            'title'       => $title,
            'description' => $description,
            'date'        => $startDate,
            'start_date'  => $startDate,
            'end_date'    => $endDate ?: $startDate,
            'category'    => 'Música',
            'image'       => $image,
            'url'         => $url,
            'location'    => $location,
        ];
    }

    private function parseDate($dateString) {
        try {
            return (new DateTime($dateString))->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }
}
