<?php
    /**
     * @Author : a.zinovyev
     * @Package: beansclient
     * @License: http://www.opensource.org/licenses/mit-license.php
     */

    namespace xobotyi\beansclient;


    use xobotyi\beansclient\Command;
    use xobotyi\beansclient\Exception;
    use xobotyi\beansclient\Interfaces;

    /**
     * Class BeansClient
     *
     * @package xobotyi\beansclient
     */
    class BeansClient
    {
        /**
         * @var Interfaces\Connection
         */
        private $connection;

        /**
         * @var Interfaces\Serializer|null
         */
        private $serializer;

        public const CRLF     = "\r\n";
        public const CRLF_LEN = 2;

        public const DEFAULT_PRIORITY = 2048;
        public const DEFAULT_DELAY    = 0;
        public const DEFAULT_TTR      = 30;
        public const DEFAULT_TUBE     = 'default';

        /**
         * BeansClient constructor.
         *
         * @param \xobotyi\beansclient\Interfaces\Connection      $connection
         * @param null|\xobotyi\beansclient\Interfaces\Serializer $serializer
         *
         * @throws \xobotyi\beansclient\Exception\Client
         */
        public
        function __construct(Interfaces\Connection $connection, ?Interfaces\Serializer $serializer = null) {
            $this->setConnection($connection);
            $this->setSerializer($serializer);
        }

        /**
         * @param \xobotyi\beansclient\Interfaces\Connection $connection
         *
         * @return \xobotyi\beansclient\BeansClient
         * @throws \xobotyi\beansclient\Exception\Client
         */
        public
        function setConnection(Interfaces\Connection $connection) :self {
            if (!$connection->isActive()) {
                throw new Exception\Client('Given connection is not active');
            }
            $this->connection = $connection;

            return $this;
        }

        /**
         * @return \xobotyi\beansclient\Interfaces\Connection
         */
        public
        function getConnection() :Interfaces\Connection {
            return $this->connection;
        }

        /**
         * @param null|\xobotyi\beansclient\Interfaces\Serializer $serializer
         *
         * @return \xobotyi\beansclient\BeansClient
         */
        public
        function setSerializer(?Interfaces\Serializer $serializer) :self {
            $this->serializer = $serializer;

            return $this;
        }

        /**
         * @return null|\xobotyi\beansclient\Interfaces\Serializer
         */
        public
        function getSerializer() :?Interfaces\Serializer {
            return $this->serializer;
        }

        /**
         * @param \xobotyi\beansclient\Command\CommandAbstract $cmd
         *
         * @return array|string|int|null
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function dispatchCommand(Command\CommandAbstract $cmd) {
            $request = $cmd->getCommandStr() . self::CRLF;

            $this->connection->write($request);

            $responseHeader = explode(' ', $this->connection->readln());

            // throwing exception if there is an error response
            if (in_array($responseHeader[0], Response::ERROR_RESPONSES)) {
                throw new Exception\Server("Got {$responseHeader[0]} in response to {$cmd->getCommandStr()}");
            }

            // if request contains data - read it
            if (in_array($responseHeader[0], Response::DATA_RESPONSES)) {
                if (count($responseHeader) === 1) {
                    throw new Exception\Client("Got no data length in response to {$cmd->getCommandStr()} [" . implode(' ', $responseHeader) . "]");
                }

                $data = $this->connection->read($responseHeader[count($responseHeader) - 1]);
                $crlf = $this->connection->read(self::CRLF_LEN);

                if ($crlf !== self::CRLF) {
                    throw new Exception\Client(sprintf('Expected CRLF[%s] after %u byte(s) of data, got %s',
                                                       str_replace(["\r", "\n", "\t"], [
                                                           "\\r",
                                                           "\\n",
                                                           "\\t",
                                                       ], self::CRLF),
                                                       $responseHeader[1],
                                                       str_replace(["\r", "\n", "\t"], ["\\r", "\\n", "\\t"], $crlf)));
                }
            }
            else {
                $data = null;
            }

            return $cmd->parseResponse($responseHeader, $data);
        }

        // COMMANDS
        // jobs
        /**
         * @param     $payload
         * @param int $priority
         * @param int $delay
         * @param int $ttr
         *
         * @return array|null
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function put($payload, $priority = self::DEFAULT_PRIORITY, int $delay = self::DEFAULT_DELAY, int $ttr = self::DEFAULT_TTR) {
            return $this->dispatchCommand(new Command\Put($payload, $priority, $delay, $ttr, $this->serializer));
        }

        /**
         * @param int|null $timeout
         *
         * @return array|null
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function reserve(?int $timeout = 0) {
            return $this->dispatchCommand(new Command\Reserve($timeout, $this->serializer));
        }

        /**
         * @param int $jobId
         *
         * @return array|null
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function delete(int $jobId) {
            return $this->dispatchCommand(new Command\Delete($jobId));
        }

        /**
         * @param int $jobId
         * @param int $priority
         * @param int $delay
         *
         * @return array|null
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function release(int $jobId, $priority = self::DEFAULT_PRIORITY, int $delay = self::DEFAULT_DELAY) {
            return $this->dispatchCommand(new Command\Release($jobId, $priority, $delay));
        }

        /**
         * @param int $jobId
         * @param int $priority
         *
         * @return array|null
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function bury(int $jobId, $priority = self::DEFAULT_PRIORITY) {
            return $this->dispatchCommand(new Command\Bury($jobId, $priority));
        }

        /**
         * @param int $jobId
         *
         * @return array|null
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function touch(int $jobId) {
            return $this->dispatchCommand(new Command\Touch($jobId));
        }

        /**
         * @param int $count
         *
         * @return array|null
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function kick(int $count) {
            return $this->dispatchCommand(new Command\Kick($count));
        }

        /**
         * @param int $jobId
         *
         * @return array|null
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function kickJob(int $jobId) {
            return $this->dispatchCommand(new Command\KickJob($jobId));
        }

        /**
         * @return array|null
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function stats() {
            return $this->dispatchCommand(new Command\Stats());
        }

        /**
         * @param int $jobId
         *
         * @return array|null
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function statsJob(int $jobId) {
            return $this->dispatchCommand(new Command\StatsJob($jobId));
        }

        // tubes

        /**
         * @param string $tube
         *
         * @return \xobotyi\beansclient\BeansClient
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function useTube(string $tube) :self {
            if ($tube !== $this->dispatchCommand(new Command\UseTube($tube))) {
                throw new Exception\Command("Tube used not matches requested tube");
            }

            return $this;
        }

        /**
         * @param string $tube
         *
         * @return \xobotyi\beansclient\BeansClient
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function watchTube(string $tube) :self {
            $this->dispatchCommand(new Command\WatchTube($tube));

            return $this;
        }

        /**
         * @param string $tube
         *
         * @return \xobotyi\beansclient\BeansClient
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function ignoreTube(string $tube) :self {
            $this->dispatchCommand(new Command\IgnoreTube($tube));

            return $this;
        }

        /**
         * @return string
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function listTubeUsed() :string {
            return $this->dispatchCommand(new Command\ListTubeUsed());
        }

        /**
         * @return array
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function listTubes() :array {
            return $this->dispatchCommand(new Command\ListTubes());
        }

        /**
         * @return array
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function listTubesWatched() :array {
            return $this->dispatchCommand(new Command\ListTubesWatched());
        }

        /**
         * @param string $tubeName
         *
         * @return array|null
         * @throws \xobotyi\beansclient\Exception\Client
         * @throws \xobotyi\beansclient\Exception\Command
         * @throws \xobotyi\beansclient\Exception\Server
         */
        public
        function statsTube(string $tubeName) :?array {
            return $this->dispatchCommand(new Command\StatsTube($tubeName));
        }
    }