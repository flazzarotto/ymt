<?php
/**
 * Created by PhpStorm.
 * User: fabrice
 * Date: 10/11/17
 * Time: 11:55
 */

namespace AppBundle\Command;

use AppBundle\Service\RandomWord;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Google;

define('CREDENTIALS_PATH', '~/php-yt-oauth2.json');

class YouTubeRandomizerCommand extends ContainerAwareCommand
{

    /**
     * @var ObjectManager
     */
    private $entityManager;

    /**
     * @var RandomWord
     */
    private $randomWord;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('youtube:randomizer')
            ->setDescription('Get a pseudo-random youtube video')
            ->addArgument('words', InputArgument::OPTIONAL, 'recherche', false);
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->entityManager = $this->getContainer()->get('doctrine')->getManager();
        $this->randomWord = $this->getContainer()->get('app.random_word');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->entityManager;

        if (!($words = $input->getArgument('words')) || !strlen($words)) {
            $randomWord = $this->randomWord;

            $words = $randomWord->pickRandomWords('fr', 3);
        }
        $clientSecret = $this->getContainer()->get('kernel')->getRootDir() . "/Resources/google/client_secret.json";

        $client = $this->getClient($clientSecret);

        $service = new \Google_Service_YouTube($client);

        $pageToken = false;

        $keptVideos = [];

        dump($words);

        $processedVideos = 0;

        do {

            $filter = [
                'safeSearch' => 'none',
                'q' => $words,
                'order' => 'date'
            ];

            if ($pageToken) {
                $filter['pageToken'] = $pageToken;
            }
            $data =
                $service->search->listSearch('id',
                    $filter
                );

            $ids = [];
            foreach ($data as $item) {
                $ids[] = $item->id->videoId;
            }

            if (count($ids)) {
                $stats = $service->videos->listVideos('contentDetails,statistics', [
                    'id' => implode(',', $ids)
                ]);
                foreach ($stats as $item) {

                    if ($item->statistics->viewCount && $item->statistics->viewCount < 500) {
                        $keptVideos[$item->id] = true;
                    }
                }
            }

            $processedVideos += count($ids);

            //dump('Processed videos: ' . $processedVideos);

            if ($processedVideos > 200) {
                break;
            }

            $pageToken = $data->getNextPageToken();

        } while ($data->getNextPageToken());


        foreach (array_keys($keptVideos) as $videoId) {
            echo "\n" . "https://www.youtube.com/watch?v=" . $videoId;
        }

        echo "\n";

        return null;
    }

    private
    function getClient($clientSecret)
    {
        $client = new \Google_Client();
        // Set to name/location of your client_secrets.json file.
        $client->setAuthConfig($clientSecret);
        // Set to valid redirect URI for your project.
        $client->setRedirectUri('http://localhost');

        $client->addScope(\Google_Service_YouTube::YOUTUBE_READONLY);
        $client->setAccessType('offline');

        // Load previously authorized credentials from a file.
        $credentialsPath = $this->expandHomeDirectory(CREDENTIALS_PATH);
        if (file_exists($credentialsPath)) {
            $accessToken = file_get_contents($credentialsPath);
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = urldecode(trim(fgets(STDIN)));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

            // Store the credentials to disk.
            if (!file_exists(dirname($credentialsPath))) {
                mkdir(dirname($credentialsPath), 0700, true);
            }
            file_put_contents($credentialsPath, json_encode($accessToken));
            printf("Credentials saved to %s\n", $credentialsPath);
        }

        $client->setAccessToken($accessToken);

        // Refresh the token if it's expired.
        if ($client->isAccessTokenExpired()) {
            $client->refreshToken($client->getRefreshToken());
            file_put_contents($credentialsPath, $client->getAccessToken());
        }
        return $client;
    }


    private
    function expandHomeDirectory($path)
    {
        $homeDirectory = getenv('HOME');
        if (empty($homeDirectory)) {
            $homeDirectory = getenv("HOMEDRIVE") . getenv("HOMEPATH");
        }
        return str_replace('~', realpath($homeDirectory), $path);
    }


}