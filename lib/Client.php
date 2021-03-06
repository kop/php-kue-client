<?php

namespace kop\kue;

use GuzzleHttp\Client as HttpClient;
use kop\kue\exceptions\ApiException;
use kop\kue\resources\Job;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class `Client`
 * ==============
 *
 * This class represents a client for Kue JSON API.
 *
 *
 * @link    https://kop.github.io/php-kue-client/ Project page.
 * @license https://github.com/kop/php-kue-client/blob/master/LICENSE.md MIT
 *
 * @author  Ivan Koptiev <ivan.koptiev@codex.systems>
 */
class Client
{
    /**
     * @var HttpClient $httpClient Guzzle HTTP client.
     */
    private $httpClient;

    /**
     * @var LoggerInterface $logger PSR-3 compatible logger.
     */
    private $logger;

    /**
     * @var bool $throwExceptions Whether to throw exceptions in case of API request errors or just return `false`.
     */
    private $throwExceptions;

    /**
     * @var array $resources Instances of Kue API resources.
     */
    private $resources = [];

    /**
     * Class constructor.
     *
     * @param string $apiURI URI of the Kue JSON API server.
     * @param array $clientOptions Options that are used as default request options with every request created by the client.
     * You can use this param to configure proxy, authentication or other request settings.
     * See {@link http://docs.guzzlephp.org/en/latest/request-options.html Guzzle documentation} for options available.
     * @param LoggerInterface $logger PSR-3 compatible logger to use. Logging is disabled by default.
     * @param bool $throwExceptions Whether to throw exceptions in case of API request errors or just return `false`.
     * Exceptions are disabled by default.
     */
    public function __construct($apiURI, array $clientOptions = [], LoggerInterface $logger = null, $throwExceptions = false)
    {
        $this->httpClient = new HttpClient(array_merge($clientOptions, [
            'base_uri' => $apiURI,
            'headers' => [
                'Accept' => 'application/json',
            ]
        ]));

        $this->setLogger($logger);
        $this->setThrowExceptions($throwExceptions);
    }

    /**
     * Responds with state counts, and worker activity time in milliseconds.
     *
     * @return bool|mixed API response or `false` if API request fails.
     * @throws \kop\kue\exceptions\ApiException If API request fails and {@link Client::$_throwExceptions} is enabled.
     */
    public function stats()
    {
        return $this->request('GET', 'stats');
    }

    /**
     * Returns a `Job` resource object.
     *
     * This object implements commands that are related to the Kue jobs.
     *
     * @return Job
     */
    public function jobs()
    {
        if (!isset($this->resources[Job::class])) {
            $this->resources[Job::class] = new Job($this);
        }

        return $this->resources[Job::class];
    }

    /**
     * Returns Guzzle HTTP client.
     *
     * @return HttpClient
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * Sets a logger instance to use.
     *
     * @param LoggerInterface|null $logger PSR-3 compatible logger or null to disable logging.
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Returns a logger instance.
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if (!$this->logger) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    /**
     * Sets an option whether to throw API exceptions or not.
     *
     * @param bool $enabled Whether to throw exceptions.
     */
    public function setThrowExceptions($enabled = true)
    {
        $this->throwExceptions = $enabled;
    }

    /**
     * Returns an option whether to throw API exceptions or not.
     *
     * @return bool Whether to throw exceptions
     */
    public function getThrowExceptions()
    {
        return $this->throwExceptions;
    }

    /**
     * Executes a request against the Kue JSON API.
     *
     * @param string $method HTTP method to use (e.g. GET, POST, etc).
     * @param string $path Request path.
     * @param array $options Additional request options.
     *
     * @return bool|mixed API response or `false` if API request fails.
     * @throws \kop\kue\exceptions\ApiException If API request fails and {@link Client::$_throwExceptions} is enabled.
     */
    public function request($method, $path, array $options = [])
    {
        // Make request
        $this->getLogger()->debug("Executing Kue API request: {$method} {$path}", $options);
        $response = $this->httpClient->request($method, $path, $options);

        // Success
        if ($response->getStatusCode() === 200) {
            $this->getLogger()->debug('API request successfully completed.', [
                'response' => $response->getBody(),
            ]);
            return json_decode($response->getBody());
        }

        // Failure
        $this->getLogger()->error('Kue API error #' . $response->getStatusCode(), [
            'reason' => $response->getReasonPhrase(),
            'body' => $response->getBody(),
        ]);
        if ($this->getThrowExceptions()) {
            throw new ApiException($response->getStatusCode(), $response->getBody());
        }

        return false;
    }
}