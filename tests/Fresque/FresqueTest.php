<?php

namespace Tests\Fresque;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Freelancehunt\Fresque\ResqueStatus;
use Freelancehunt\Fresque\Fresque;
use Freelancehunt\Fresque\ResqueStats;
use ezcConsoleOutput;
use ezcConsoleInput;
use ReflectionMethod;
use Freelancehunt\Fresque\SendSignalCommandOptions;
use stdClass;

class FresqueTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['argv'] = [];

        $this->output = $this->createMock(ezcConsoleOutput::class);
        $this->input  = $this->createMock(ezcConsoleInput::class);

        $this->shell         = $this->getMockBuilder(Fresque::class)->onlyMethods(['callCommand', 'outputTitle', 'kill', 'getUserChoice', 'testConfig', 'enqueueJob', 'getResqueStat'])->getMock();
        $this->shell->output = $this->output;
        $this->shell->input  = $this->input;

        $this->shell->ResqueStatus = $this->ResqueStatus = $this->createMock(ResqueStatus::class);
        $this->shell->ResqueStats  = $this->ResqueStats = $this->getMockBuilder(ResqueStats::class)->disableOriginalConstructor()->getMock();
        $this->ResqueStats->expects($this->any())->method('getWorkerStartDate')->willReturn('2022-05-01');

        $this->startArgs = [

            'Default'   => [
                'queue'    => 'default',
                'workers'  => 1,
                'interval' => 5,
                'verbose'  => true,
                'user'     => '',
            ],
            'Fresque'   => [
                'lib'     => '',
                'include' => '',
            ],
            'Redis'     => [
                'host'      => '',
                'database'  => 0,
                'password'  => null,
                'port'      => 0,
                'namespace' => '',
            ],
            'Log'       => [
                'handler'  => '',
                'target'   => '',
                'filename' => '',
            ],
            'Scheduler' => [
                'enabled' => false,
                'lib'     => './vendor/kamisama/phhp-resque-ex-scheduler',
                'log'     => '',
            ],
        ];

        $this->sendSignalOptions = new SendSignalCommandOptions();

        $this->sendSignalOptions->title            = 'Testing workers';
        $this->sendSignalOptions->noWorkersMessage = 'There is no workers to test';
        $this->sendSignalOptions->allOption        = 'Test all workers';
        $this->sendSignalOptions->selectMessage    = 'Worker to test';
        $this->sendSignalOptions->actionMessage    = 'testing';
        $this->sendSignalOptions->listTitle        = 'list of workers to test';
        $this->sendSignalOptions->workers          = [];
        $this->sendSignalOptions->signal           = 'TEST';
        $this->sendSignalOptions->successCallback  = function ($pid) {
        };
    }

    /**
     * Should not print debug information when debug is enabled
     *
     * @covers \Fresque\Fresque::debug
     * @return  void
     */
    public function testDebug()
    {
        $this->output
            ->expects($this->exactly(1))
            ->method('outputLine')
            ->withConsecutive([$this->stringContains('[DEBUG] test string')]);

        $this->shell->debug = true;
        $this->shell->debug('test string');
    }

    /**
     * Should not print debug information when debug is disabled
     *
     * @covers \Fresque\Fresque::debug
     * @return  void
     */
    public function testDebugWhenDisabled()
    {
        $this->output->expects($this->never())->method('outputLine');
        $this->shell->debug('test string');
    }

    /**
     * Check if a resque bin file is in the bin folder
     *
     * @covers \Fresque\Fresque::getResqueBinFile
     * @return  void
     */
    public function testGetResqueBin()
    {
        $method = new ReflectionMethod(Fresque::class, 'getResqueBinFile');
        $method->setAccessible(true);

        $root = vfsStream::setup('resque');
        $root->addChild(vfsStream::newDirectory('bin'));
        $root->getChild('bin')->addChild(vfsStream::newFile('resque'));

        $this->assertTrue($root->hasChild('bin'));
        $this->assertTrue($root->getChild('bin')->hasChild('resque'));
        $this->assertEquals('./bin/resque', $method->invoke($this->shell, vfsStream::url('resque')));
    }

    /**
     * Check if a resque bin file is in the bin folder,
     * but with a .php extension
     *
     * @covers \Fresque\Fresque::getResqueBinFile
     * @return  void
     */
    public function testGetResqueBinWithExtension()
    {
        $method = new ReflectionMethod(Fresque::class, 'getResqueBinFile');
        $method->setAccessible(true);

        $root = vfsStream::setup('resque');
        $root->addChild(vfsStream::newDirectory('bin'));
        $root->getChild('bin')->addChild(vfsStream::newFile('resque.php'));

        $this->assertTrue($root->hasChild('bin'));
        $this->assertTrue($root->getChild('bin')->hasChild('resque.php'));
        $this->assertEquals('./bin/resque.php', $method->invoke($this->shell, vfsStream::url('resque')));
    }

    /**
     * For old version of php-resque, when the file is in the root
     *
     * @covers \Fresque\Fresque::getResqueBinFile
     * @return  void
     */
    public function testGetResqueBinFallbtestStopWhenNoWorkersackInRoot()
    {
        $method = new ReflectionMethod(Fresque::class, 'getResqueBinFile');
        $method->setAccessible(true);

        $root = vfsStream::setup('resque');
        $this->assertEquals('./resque.php', $method->invoke($this->shell, vfsStream::url('resque')));
    }

    /**
     * Print a title
     *
     * @covers \Fresque\Fresque::outputTitle
     * @return  void
     */
    public function testOutputMainTitle()
    {
        $title = 'my first title';

        $this->output
            ->expects($this->exactly(3))
            ->method('outputLine')
            ->withConsecutive(
                [$this->equalTo(str_repeat('-', strlen($title)))],
                [$this->equalTo($title), $this->equalTo('title')],
                [$this->equalTo(str_repeat('-', strlen($title)))]
            );

        $this->shell         = $this->getMockBuilder(Fresque::class)->onlyMethods(['callCommand'])->getMock();
        $this->shell->output = $this->output;
        $this->shell->outputTitle($title);
    }

    /**
     * Print a subtitle
     *
     * @covers \Fresque\Fresque::outputTitle
     * @return  void
     */
    public function testOutputSubTitle()
    {
        $title = 'my first title';
        $this->output->expects($this->exactly(1))->method('outputLine')->with($this->equalTo($title), $this->equalTo('subtitle'));

        $this->shell         = $this->getMockBuilder(Fresque::class)->onlyMethods(['callCommand'])->getMock();
        $this->shell->output = $this->output;
        $this->shell->outputTitle($title, false);
    }

    /**
     * Start a worker
     *
     * @covers \Fresque\Fresque::start
     * @return void
     */
    public function testStart()
    {
        $this->shell         = $this->getMockBuilder(Fresque::class)->onlyMethods(['callCommand', 'outputTitle', 'exec', 'checkStartedWorker', 'getProcessOwner'])->getMock();
        $this->shell->output = $this->output;

        $this->shell->expects($this->never())->method('outputTitle');

        $this->shell->expects($this->once())->method('exec')->will($this->returnValue(true));
        $this->shell->expects($this->once())->method('checkStartedWorker')->will($this->returnValue(true));

        $this->output
            ->expects($this->exactly(1))
            ->method('outputLine')
            ->withConsecutive(
                [$this->stringContains('done')],
            );

        $this->output
            ->expects($this->exactly(4))
            ->method('outputText')
            ->withConsecutive(
                [$this->stringContains('starting worker')],
                [$this->stringContains('.')],
                [$this->stringContains('.')],
                [$this->stringContains('.')],
            );

        $this->ResqueStatus = $this->getMockBuilder(ResqueStatus::class)->disableOriginalConstructor()->onlyMethods(['isRunningSchedulerWorker', 'addWorker'])->getMock();

        $this->ResqueStatus->expects($this->once())->method('addWorker');
        $this->shell->ResqueStatus = $this->ResqueStatus;

        $this->shell->start($this->startArgs);
    }

    /**
     * Start a scheduler worker
     *
     * @covers \Fresque\Fresque::startScheduler
     * @return void
     */
    public function testStartScheduler()
    {
        $this->shell         = $this->getMockBuilder(Fresque::class)
            ->onlyMethods(['callCommand', 'outputTitle', 'exec', 'checkStartedWorker', 'getProcessOwner'])
            ->getMock();
        $this->shell->output = $this->output;

        $pid = rand(0, 100);

        $this->shell->expects($this->never())->method('outputTitle');

        $this->shell->expects($this->once())->method('exec')->will($this->returnValue(true));
        $this->shell->expects($this->once())->method('checkStartedWorker')->will($this->returnValue($pid));

        $this->output
            ->expects($this->exactly(1))
            ->method('outputLine')
            ->withConsecutive(
                [$this->stringContains('done')],
            );

        $this->output
            ->expects($this->exactly(4))
            ->method('outputText')
            ->withConsecutive(
                [$this->stringContains('Starting scheduler worker')],
                [$this->stringContains('.')],
                [$this->stringContains('.')],
                [$this->stringContains('.')],
            );

        $this->ResqueStatus = $this
            ->getMockBuilder(ResqueStatus::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isRunningSchedulerWorker', 'registerSchedulerWorker', 'addWorker'])
            ->getMock();

        $this->ResqueStatus->expects($this->once())->method('isRunningSchedulerWorker')->will($this->returnValue(false));
        $this->ResqueStatus->expects($this->once())->method('registerSchedulerWorker')->with($this->equalTo($pid));
        $this->ResqueStatus->expects($this->once())->method('addWorker');
        $this->shell->ResqueStatus = $this->ResqueStatus;

        $this->startArgs['Scheduler']['enabled'] = true;
        $this->shell->start($this->startArgs, true);
    }

    public function testRestartWhenNoStartedWorkers()
    {
        $this->shell               = $this->getMockBuilder(Fresque::class)->onlyMethods(['callCommand', 'start', 'stop', 'outputTitle'])->getMock();
        $this->shell->output       = $this->output;
        $this->shell->ResqueStatus = $this->ResqueStatus;

        $this->ResqueStatus->expects($this->once())->method('getWorkers')->will($this->returnValue([]));
        $this->shell->expects($this->once())->method('outputTitle')->with($this->stringContains('restarting workers'));

        $this->output
            ->expects($this->exactly(2))
            ->method('outputLine')
            ->withConsecutive(
                [$this->stringContains('no workers to restart')],
            );

        $this->shell->expects($this->never())->method('start');
        $this->shell->expects($this->never())->method('stop');
        $this->shell->ResqueStatus = $this->ResqueStatus;
        $this->shell->restart();
    }

    public function testRestart()
    {
        $this->shell               = $this->getMockBuilder(Fresque::class)->onlyMethods(['callCommand', 'start', 'stop', 'outputTitle'])->getMock();
        $this->shell->output       = $this->output;
        $this->shell->ResqueStatus = $this->ResqueStatus;
        $workers                   = [0, 1];

        $this->ResqueStatus->expects($this->once())->method('getWorkers')->will($this->returnValue($workers));
        $this->shell->expects($this->once())->method('outputTitle')->with($this->stringContains('restarting workers'));
        $this->output->expects($this->once())->method('outputLine');

        $this->shell
            ->expects($this->exactly(2))
            ->method('start')
            ->withConsecutive(
                [$this->equalTo($workers[0])],
                [$this->equalTo($workers[1])],
            );
        $this->shell->expects($this->once())->method('stop');
        $this->shell->ResqueStatus = $this->ResqueStatus;
        $this->shell->restart();
    }

    /**
     * Load should returns an error message when there is nothing to load
     */
    public function testLoadWhenNothingToLoad()
    {
        $this->shell               = $this->getMockBuilder(Fresque::class)->onlyMethods(['callCommand', 'start', 'stop', 'outputTitle'])->getMock();
        $this->shell->output       = $this->output;
        $this->shell->ResqueStatus = $this->ResqueStatus;
        $workers                   = [0, 1];

        $this->shell->expects($this->once())->method('outputTitle')->with($this->stringContains('Loading predefined workers'));

        $this->output
            ->expects($this->exactly(2))
            ->method('outputLine')
            ->withConsecutive(
                [$this->stringContains('You have no configured workers to load')],
            );

        $this->shell->ResqueStatus                    = $this->ResqueStatus;
        $this->shell->runtime['Queues']               = [];
        $this->shell->runtime['Scheduler']['enabled'] = false;

        $this->shell->load();
    }

    public function testLoad()
    {
        $this->shell = $this->getMockBuilder(Fresque::class)->onlyMethods(['callCommand', 'start', 'stop', 'outputTitle', 'loadSettings'])->getMock();

        $this->shell->output       = $this->output;
        $this->shell->ResqueStatus = $this->ResqueStatus;
        $workers                   = [0, 1];

        $this->shell->expects($this->once())->method('outputTitle')->with($this->stringContains('Loading predefined workers'));
        $this->shell->expects($this->exactly(2))->method('start');

        $this->output
            ->expects($this->exactly(2))
            ->method('outputLine')
            ->withConsecutive(
                [$this->stringContains('Loading 2 workers')],
            );

        $this->shell->ResqueStatus                    = $this->ResqueStatus;
        $this->shell->config_file                     = '';
        $queue                                        = [
            'name'   => 'default',
            'config' => '',
            'debug'  => false,
        ];
        $this->shell->runtime['Queues']               = [$queue, $queue];
        $this->shell->runtime['Scheduler']['enabled'] = false;
        $this->shell->load();
    }

    /**
     * Queuing a job without arguments, will fail
     *
     * @covers \Fresque\Fresque::enqueue
     * @return  void
     */
    public function testEnqueueJobWithoutArguments()
    {
        $this->shell->expects($this->once())->method('outputTitle')->with($this->stringContains('Queuing a job'));

        $this->output
            ->expects($this->exactly(6))
            ->method('outputLine')
            ->withConsecutive(
                [$this->stringContains('Enqueue takes at least 2 arguments')],
                [$this->stringContains('usage')],
            );

        $this->shell->enqueue();
    }

    /**
     * Queuing a job with wrong number of arguments, will fail
     *
     * @covers \Fresque\Fresque::enqueue
     * @return  void
     */
    public function testEnqueueJob()
    {
        $id  = md5(time());
        $job = ['queue', 'class'];

        $this->shell->expects($this->once())->method('enqueueJob')->with($this->equalTo($job[0]), $this->equalTo($job[1]), $this->equalTo([]))->will($this->returnValue($id));
        $this->input->expects($this->once())->method('getArguments')->will($this->returnValue($job));

        $this->shell->expects($this->once())->method('outputTitle')->with($this->stringContains('Queuing a job'));

        $this->output
            ->expects($this->exactly(2))
            ->method('outputLine')
            ->withConsecutive(
                [$this->stringContains('The job was enqueued successfully')],
                [$this->stringContains('job id : #' . $id)],
            );

        $this->shell->enqueue();
    }

    /**
     * Printing help message
     *
     * @covers \Fresque\Fresque::help
     * @return  void
     */
    public function testHelp()
    {
        $this->shell->expects($this->once())->method('outputTitle')->with($this->equalTo('Welcome to Fresque'));
        $this->shell->commandTree = [
            'start' => [
                'help'    => 'Start a new worker',
                'options' => ['u' => 'username', 'q' => 'queue name',
                              'i' => 'num', 'n' => 'num', 'l' => 'path', 'v', 'g']],
            'stop'  => [
                'help'    => 'Shutdown all workers',
                'options' => ['f', 'w', 'g']],
        ];

        $this->output
            ->expects($this->exactly(4))
            ->method('outputLine')
            ->withConsecutive(
                [],
                [],
                [$this->stringContains('Available commands')],
                [$this->stringContains('Use <command> --help to get more infos about a command')],
            );

        $this->output
            ->expects($this->exactly(4))
            ->method('outputText')
            ->withConsecutive(
                [$this->stringContains('start')],
                [$this->stringContains($this->shell->commandTree['start']['help'])],
                [$this->stringContains('stop')],
                [$this->stringContains($this->shell->commandTree['stop']['help'])],
            );

        $this->shell->help();
    }

    /**
     * Printing help message when calling a unrecognized command
     *
     * @covers \Fresque\Fresque::help
     * @return  void
     */
    public function testPrintHelpWhenCallingUnhrecognizedCommand()
    {
        $this->shell->expects($this->once())->method('outputTitle')->with($this->equalTo('Welcome to Fresque'));

        $this->output
            ->expects($this->atLeastOnce())
            ->method('outputLine')
            ->withConsecutive(
                [],
                [$this->stringContains('Unrecognized command : hello')],
            );

        $this->shell->commandTree = [];
        $this->shell->help('hello');
    }

    /**
     * @covers \Fresque\Fresque::sendSignal
     * @return  void
     */
    public function testSendSignalWhenNoWorkers()
    {
        $option        = new stdClass();
        $option->value = false;
        $this->input->expects($this->exactly(2))->method('getOption')->will($this->returnValue($option));

        $this->shell->expects($this->once())->method('outputTitle')->with($this->stringContains($this->sendSignalOptions->title));
        $this->output
            ->expects($this->exactly(2))
            ->method('outputLine')
            ->withConsecutive(
                [$this->stringContains($this->sendSignalOptions->noWorkersMessage)],
            );

        $this->shell->sendSignal($this->sendSignalOptions);
    }

    /**
     * @covers \Fresque\Fresque::sendSignal
     * @return  void
     */
    public function testSendSignalWhenOnlyOneWorker()
    {
        $option        = new stdClass();
        $option->value = false;
        $this->input->expects($this->exactly(2))->method('getOption')->will($this->returnValue($option));

        $this->shell->expects($this->once())->method('outputTitle')->with($this->stringContains('testing workers'));
        $this->output
            ->expects($this->exactly(1))
            ->method('outputText')
            ->withConsecutive(
                [$this->stringContains('testing 100 ...')],
            );
        $this->output
            ->expects($this->exactly(2))
            ->method('outputLine')
            ->willReturnOnConsecutiveCalls(
                [$this->returnValue('done')],
            );

        $this->shell->expects($this->once())->method('kill')->with($this->equalTo($this->sendSignalOptions->signal), $this->equalTo('100'))->will($this->returnValue(['code' => 0, 'message' => '']));

        $this->sendSignalOptions->workers = ['host:100:queue'];

        $this->shell->runtime = $this->startArgs;
        $this->shell->sendSignal($this->sendSignalOptions);
    }

    /**
     * @covers \Fresque\Fresque::sendSignal
     * @return  void
     */
    public function testSendSignalDisplayErrorMessageOnFail()
    {
        $option        = new stdClass();
        $option->value = false;
        $this->input->expects($this->exactly(2))->method('getOption')->will($this->returnValue($option));

        $this->shell->expects($this->once())->method('outputTitle')->with($this->stringContains('testing workers'));

        $this->output
            ->expects($this->exactly(1))
            ->method('outputText')
            ->withConsecutive(
                [$this->stringContains('testing 100 ...')],
            );
        $this->output
            ->expects($this->exactly(2))
            ->method('outputLine')
            ->willReturnOnConsecutiveCalls(
                [$this->returnValue('error message')],
            );

        $this->shell->expects($this->once())->method('kill')
            ->with($this->equalTo($this->sendSignalOptions->signal), $this->equalTo('100'))
            ->will($this->returnValue(['code' => 1, 'message' => 'Error message']));

        $this->sendSignalOptions->workers = ['host:100:queue'];

        $this->shell->runtime = $this->startArgs;
        $this->shell->sendSignal($this->sendSignalOptions);
    }

    /**
     * @covers \Fresque\Fresque::sendSignal
     * @return  void
     */
    public function testSendSignalToAllWorkersWithAllOption()
    {
        $option        = new stdClass();
        $option->value = true;

        $this->input->expects($this->exactly(2))->method('getOption')->will($this->returnValue($option));
        $this->shell->expects($this->once())->method('outputTitle')->with($this->stringContains('testing workers'));

        $this->output
            ->expects($this->exactly(4))
            ->method('outputLine')
            ->willReturnOnConsecutiveCalls(
                [],
                [$this->returnValue('done')],
                [$this->returnValue('done')],
                [$this->returnValue('done')],
            );

        $this->output
            ->expects($this->exactly(3))
            ->method('outputText')
            ->withConsecutive(
                [$this->stringContains('testing 100 ...')],
                [$this->stringContains('testing 101 ...')],
                [$this->stringContains('testing 102 ...')],
            );

        $this->shell->expects($this->exactly(3))->method('kill')->will($this->returnValue(['code' => 0, 'message' => '']));

        $this->sendSignalOptions->workers = [
            'host:100:queue',
            'host:101:queue',
            'host:102:queue',
        ];

        $this->shell->runtime = $this->startArgs;
        $this->shell->sendSignal($this->sendSignalOptions);
    }

    /**
     * @covers \Fresque\Fresque::sendSignal
     * @return  void
     */
    public function testSendSignalToAllWorkersWithAllInput()
    {
        $option        = new stdClass();
        $option->value = false;

        $this->shell->expects($this->once())->method('getUserChoice')->will($this->returnValue('all'));
        $this->shell->expects($this->once())->method('outputTitle')->with($this->stringContains('testing workers'));

        $this->input
            ->expects($this->exactly(2))
            ->method('getOption')
            ->withConsecutive(
                [$this->equalTo('force')],
                [$this->equalTo('all')],
            )
            ->willReturnOnConsecutiveCalls(
                $this->returnValue($option),
                $this->returnValue($option),
            );

        $this->output
            ->expects($this->exactly(4))
            ->method('outputLine')
            ->willReturnOnConsecutiveCalls(
                [],
                $this->returnValue('done'),
                $this->returnValue('done'),
                $this->returnValue('done'),
            );

        $this->output
            ->expects($this->exactly(3))
            ->method('outputText')
            ->withConsecutive(
                [$this->stringContains('testing 100 ...')],
                [$this->stringContains('testing 101 ...')],
                [$this->stringContains('testing 102 ...')],
            );

        $this->shell->expects($this->exactly(3))->method('kill')->will($this->returnValue(['code' => 0, 'message' => '']));

        $this->sendSignalOptions->workers = [
            'host:100:queue',
            'host:101:queue',
            'host:102:queue',
        ];

        $this->shell->runtime = $this->startArgs;
        $this->shell->sendSignal($this->sendSignalOptions);
    }

    /**
     * @covers \Fresque\Fresque::sendSignal
     * @return  void
     */
    public function testSendSignalToOneWorkerWhenMultipleWorker()
    {
        $option        = new stdClass();
        $option->value = false;

        $this->shell->expects($this->once())->method('getUserChoice')->will($this->returnValue('2'));

        $this->input
            ->expects($this->exactly(2))
            ->method('getOption')
            ->withConsecutive(
                [$this->equalTo('force')],
                [$this->equalTo('all')],
            )
            ->willReturnOnConsecutiveCalls(
                $this->returnValue($option),
                $this->returnValue($option),
            );

        $this->output
            ->expects($this->exactly(2))
            ->method('outputLine')
            ->willReturnOnConsecutiveCalls(
                [],
                $this->returnValue('done'),
            );

        $this->output
            ->expects($this->exactly(1))
            ->method('outputText')
            ->withConsecutive(
                [$this->stringContains('testing 101 ...')],
            );

        $this->shell->expects($this->once())->method('outputTitle')->with($this->stringContains('testing workers'));
        $this->shell->expects($this->exactly(1))->method('kill')->will($this->returnValue(['code' => 0, 'message' => '']));

        $this->sendSignalOptions->workers = [
            'host:100:queue',
            'host:101:queue',
            'host:102:queue',
        ];

        $this->shell->runtime = $this->startArgs;
        $this->shell->sendSignal($this->sendSignalOptions);
    }

    /**
     * Stop will send the QUIT signal and the active workers list to sendSignal()
     *
     * @covers \Fresque\Fresque::stop
     */
    public function testStop()
    {
        $shell               = $this->getMockBuilder(Fresque::class)->onlyMethods(['callCommand', 'outputTitle', 'kill', 'getUserChoice', 'sendSignal', 'getActiveWorkers'])->getMock();
        $shell->ResqueStatus = $this->ResqueStatus = $this->createMock(ResqueStatus::class);

        $workers = ['test', 'testOne'];

        $shell->expects($this->once())->method('sendSignal')->with(
            $this->callback(function ($options) use ($workers) {
                return $options->signal === 'QUIT' && $options->workers === $workers;
            })
        );

        $shell->expects($this->once())->method('getActiveWorkers')->will($this->returnValue($workers));

        $shell->stop();
    }

    /**
     * Stop will send the TERM signal if 'force' option is selected
     *
     * @covers \Fresque\Fresque::stop
     */
    public function testForceStop()
    {
        $option        = new stdClass();
        $option->value = true;

        $shell        = $this->getMockBuilder(Fresque::class)->onlyMethods(['callCommand', 'outputTitle', 'kill', 'getUserChoice', 'sendSignal', 'getActiveWorkers'])->getMock();
        $shell->input = $this->input;
        $shell->input->expects($this->once())->method('getOption')->with($this->equalTo('force'))->will($this->returnValue($option));
        $shell->ResqueStatus = $this->ResqueStatus = $this->createMock(ResqueStatus::class);

        $shell->expects($this->once())->method('sendSignal')->with(
            $this->callback(function ($options) {
                return $options->signal === 'TERM';
            })
        );

        $shell->expects($this->once())->method('getActiveWorkers');

        $shell->stop();
    }

    /**
     * Pause will send the USR2 signal and the active workers list to sendSignal()
     *
     * @covers \Fresque\Fresque::pause
     */
    public function testPause()
    {
        $shell               = $this->getMockBuilder(Fresque::class)->onlyMethods(['callCommand', 'outputTitle', 'kill', 'getUserChoice', 'sendSignal', 'getActiveWorkers'])->getMock();
        $shell->ResqueStatus = $this->getMockBuilder(ResqueStatus::class)->disableOriginalConstructor()->onlyMethods(['getPausedWorkers'])->getMock();
        $shell->ResqueStatus->expects($this->once())->method('getPausedWorkers')->will($this->returnValue([]));

        $workers = ['test', 'testOne'];

        $shell->expects($this->once())->method('sendSignal')->with(
            $this->callback(function ($options) use ($workers) {
                return $options->signal === 'USR2' && $options->workers === $workers;
            })
        );

        $shell->expects($this->once())->method('getActiveWorkers')->will($this->returnValue($workers));

        $shell->pause();
    }

    /**
     * Resume will send the CONT signal and the paused workers list to sendSignal()
     *
     * @covers \Fresque\Fresque::resume
     */
    public function testResume()
    {
        $workers = ['test', 'testOne'];

        $shell               = $this->getMockBuilder(Fresque::class)->onlyMethods(['callCommand', 'outputTitle', 'kill', 'getUserChoice', 'sendSignal'])->getMock();
        $shell->ResqueStatus = $this->getMockBuilder(ResqueStatus::class)->disableOriginalConstructor()->onlyMethods(['getPausedWorkers'])->getMock();
        $shell->ResqueStatus->expects($this->once())->method('getPausedWorkers')->will($this->returnValue($workers));

        $shell->expects($this->once())->method('sendSignal')->with(
            $this->callback(function ($options) use ($workers) {
                return $options->signal === 'CONT' && $options->workers === $workers;
            })
        );

        $shell->resume();
    }

    /**
     * @covers \Fresque\Fresque::stats
     */
    public function testStats()
    {
        $datas = [
            [
                'host'      => 'w1',
                'pid'       => 0,
                'queue'     => 'queue1',
                'processed' => 15,
                'failed'    => 0,
            ],
            [
                'host'      => 'w2',
                'pid'       => 0,
                'queue'     => 'queue2',
                'processed' => 9,
                'failed'    => 5,
            ],
        ];

        $queueList  = ['queue1', 'queue2', 'queue3', 'queue4'];
        $workerList = [
            new DummyWorker($datas[0]['host'] . ':' . $datas[0]['pid'] . ':' . $datas[0]['queue'], $datas[0]['processed'], $datas[0]['failed']),
            new DummyWorker($datas[1]['host'] . ':' . $datas[1]['pid'] . ':' . $datas[1]['queue'], $datas[1]['processed'], $datas[1]['failed']),
        ];

        $this->shell->ResqueStatus = $this->createMock(ResqueStatus::class);
        $this->shell->ResqueStats  = $this->getMockBuilder(ResqueStats::class)->disableOriginalConstructor()->getMock();
        $this->shell->ResqueStats->expects($this->any())->method('getWorkerStartDate')->willReturn('2022-05-01');

        $this->shell->expects($this->once())->method('outputTitle')->with($this->stringContains('Resque statistics'));

        $this->shell->ResqueStats->expects($this->once())->method('getQueues')->will($this->returnValue($queueList));
        $this->shell->ResqueStats->expects($this->once())->method('getWorkers')->will($this->returnValue($workerList));

        $this->shell->ResqueStatus->expects($this->once())->method('getPausedWorkers')->will($this->returnValue(['w1:0:queue1']));

        $this->shell->ResqueStats
            ->expects($this->exactly(4))
            ->method('getQueueLength')
            ->withConsecutive(
                [$this->stringContains('queue4')],
                [$this->stringContains('queue3')],
                [$this->stringContains($datas[1]['queue'])],
                [$this->stringContains($datas[0]['queue'])],
            )->willReturnOnConsecutiveCalls(0, 9, 10, 3);

        $this->output
            ->expects($this->any())
            ->method('outputLine')
            ->withConsecutive(
                [], [], [], [], [],
                [$this->stringContains('queues stats')],
                [$this->stringContains('queues count : 3')],
                [],
                [$this->stringContains('workers stats')],
                [$this->stringContains('active workers : ' . count($workerList))],
                [], [],
                [$this->stringContains('processed jobs : ' . $datas[0]['processed'])],
                [$this->stringContains('failed jobs    : ' . $datas[0]['failed'])],
                [], [],
                [$this->stringContains('processed jobs : ' . $datas[1]['processed'])],
                [$this->stringContains('failed jobs    : ' . $datas[1]['failed'])],
            );

        $this->output
            ->expects($this->any())
            ->method('outputText')
            ->withConsecutive(
                [$this->logicalAnd($this->stringContains($datas[0]['queue']), $this->stringContains('3 pending jobs'))],
                [],
                [$this->logicalAnd($this->stringContains($datas[1]['queue']), $this->stringContains('10 pending jobs'))],
                [],
                [$this->logicalAnd($this->stringContains('queue3'), $this->stringContains('9 pending jobs'))],
                [$this->stringContains('(unmonitored queue)')],
                [],
                [$this->stringContains('worker : ' . (string) $workerList[0])],
                [$this->stringContains('(paused)')],
                [],
                [$this->stringContains('worker : ' . (string) $workerList[1])],
            );

        $this->shell->runtime = $this->startArgs;
        $this->shell->stats();
    }

    /**
     * @covers \Fresque\Fresque::test
     */
    public function testTest()
    {
        $this->shell->expects($this->once())->method('outputTitle')->with($this->stringContains('testing configuration'));

        // $this->shell->test();
        $this->markTestIncomplete();
    }

    /**
     * @covers \Fresque\Fresque::testConfig
     */
    public function testTestConfig()
    {
        //$this->shell->testConfig();
        $this->markTestIncomplete();
    }

    /**
     * @covers \Fresque\Fresque::callCommand
     */
    public function testCallCommandWithValidCommand()
    {
        $this->shell         = $this->getMockBuilder(Fresque::class)->onlyMethods(['start', 'help', 'loadSettings', 'setResqueBackend', 'initResqueStatus', 'initResqueStats'])->getMock();
        $this->shell->output = $this->output;
        $this->shell->input  = $this->input;

        $helpOptions        = new stdClass();
        $helpOptions->value = false;

        $this->input->expects($this->once())->method('getOption')->with($this->equalTo('help'))->will($this->returnValue($helpOptions));

        $this->input->expects($this->once())->method('getOptionValues')->will($this->returnValue([]));
        $this->shell->expects($this->once())->method('start');
        $this->shell->expects($this->once())->method('setResqueBackend');
        $this->shell->expects($this->once())->method('initResqueStatus');
        $this->shell->expects($this->once())->method('initResqueStats');

        $this->shell->runtime = $this->startArgs;
        $this->shell->callCommand('start');
    }

    /**
     * @covers \Fresque\Fresque::callCommand
     */
    public function testCallCommandWithValidCommandButInvalidOptions()
    {
        $this->shell         = $this->getMockBuilder(Fresque::class)->onlyMethods(['start', 'help', 'loadSettings', 'setResqueBackend', 'initResqueStatus', 'initResqueStats'])->getMock();
        $this->shell->output = $this->output;
        $this->shell->input  = $this->input;

        $helpOptions        = new stdClass();
        $helpOptions->value = false;

        $this->input->expects($this->once())->method('getOption')->with($this->equalTo('help'))->will($this->returnValue($helpOptions));

        $options = ['tr' => '', 'br' => '', 'vr' => '', 'i' => ''];

        $this->input->expects($this->once())->method('getOptionValues')->will($this->returnValue($options));
        $this->shell->expects($this->once())->method('start');
        $this->shell->expects($this->once())->method('setResqueBackend');
        $this->shell->expects($this->once())->method('initResqueStatus');
        $this->shell->expects($this->once())->method('initResqueStats');

        $invalidOptions = $options;
        unset($invalidOptions['i']);
        $this->output->expects($this->once())->method('outputLine')->with($this->equalTo('Invalid options -' . implode(', -', array_keys($invalidOptions)) . ' will be ignored'));

        $this->shell->runtime = $this->startArgs;
        $this->shell->callCommand('start');
    }

    /**
     * @covers \Fresque\Fresque::callCommand
     */
    public function testCallCommandWithInvalidCommand()
    {
        $this->shell         = $this->getMockBuilder(Fresque::class)->onlyMethods(['help', 'loadSettings', 'setResqueBackend', 'initResqueStatus', 'initResqueStats'])->getMock();
        $this->shell->output = $this->output;
        $this->shell->input  = $this->input;

        $this->shell->expects($this->once())->method('help')->with('command');
        $this->shell->expects($this->never())->method('setResqueBackend');
        $this->shell->expects($this->never())->method('initResqueStatus');
        $this->shell->expects($this->never())->method('initResqueStats');
        $this->shell->callCommand('command');
    }

    /**
     * @covers \Fresque\Fresque::reset
     * @return void
     */
    public function testReset()
    {
        $this->ResqueStatus->expects($this->once())->method('clearWorkers');
        $this->ResqueStatus->expects($this->once())->method('unregisterSchedulerWorker');

        $this->shell->reset();
    }

    /**
     * loadSettings is using the default fresque.ini
     */
    public function testLoadSettingsUsingDefaultConfigFile()
    {
        $option        = new stdClass();
        $option->value = false;
        $this->input->expects($this->once())->method('getOption')->will($this->returnValue($option));

        $this->shell->loadSettings('');

        $this->assertEquals('./src/fresque.ini', $this->shell->config_file);
    }

    /**
     * loadSettings should die if .ini file does not exists
     * Setting from $args argument
     */
    public function testLoadSettingsUsingInexistingConfigFileFromArgs()
    {
        $iniFile       = 'inexisting_file.ini';
        $option        = new stdClass();
        $option->value = false;
        $this->input->expects($this->never())->method('getOptionValues');
        $this->input->expects($this->never())->method('getOption');
        $this->output->expects($this->once())->method('outputLine')->with($this->stringContains('The config file \'' . $iniFile . '\' was not found'));

        $return = $this->shell->loadSettings('', ['config' => $iniFile]);

        $this->assertEquals($iniFile, $this->shell->config_file);
        $this->assertEquals(false, $return);
    }

    /**
     * loadSettings should die if .ini file does not exists
     * Setting from cli option
     */
    public function testLoadSettingsUsingInexistingConfigFileFromOption()
    {
        $iniFile       = 'inexisting_file.ini';
        $option        = new stdClass();
        $option->value = false;
        $this->input->expects($this->once())->method('getOptionValues')->will(
            $this->returnValue(['config' => $iniFile])
        );
        $this->input->expects($this->never())->method('getOption');
        $this->output->expects($this->once())->method('outputLine')->with($this->stringContains('The config file \'' . $iniFile . '\' was not found'));

        $return = $this->shell->loadSettings('');

        $this->assertEquals($iniFile, $this->shell->config_file);
        $this->assertEquals(false, $return);
    }

    /**
     * loadSettings is using debug false by default
     */
    public function testLoadSettingsWithDebugToFalse()
    {
        $option        = new stdClass();
        $option->value = false;
        $this->input->expects($this->once())->method('getOptionValues');
        $this->input->expects($this->once())->method('getOption')->with($this->equalTo('verbose'))->will($this->returnValue($option));

        $return = $this->shell->loadSettings('');

        $this->assertEquals(false, $this->shell->debug);
        $this->assertEquals(true, $return);
    }

    /**
     * loadSettings is using debug setting from arguments
     */
    public function testLoadSettingsWithDebugFromArgs()
    {
        $option        = new stdClass();
        $option->value = false;
        $this->input->expects($this->never())->method('getOptionValues');
        $this->input->expects($this->once())->method('getOption')->with($this->equalTo('verbose'))->will($this->returnValue($option));

        $return = $this->shell->loadSettings('', ['debug' => true]);

        $this->assertEquals(true, $this->shell->debug);
        $this->assertEquals(true, $return);
    }

    /**
     * loadSettings is using debug setting from arguments
     */
    public function testLoadSettingsWithDebugFromOption()
    {
        $option        = new stdClass();
        $option->value = false;
        $this->input->expects($this->once())->method('getOptionValues')->will($this->returnValue([
            'debug' => true,
        ]));
        $this->input->expects($this->once())->method('getOption')->with($this->equalTo('verbose'))->will($this->returnValue($option));

        $return = $this->shell->loadSettings('');

        $this->assertEquals(true, $this->shell->debug);
        $this->assertEquals(true, $return);
    }

    /**
     * loadSettings is using default verbose from .ini file
     */
    public function testLoadSettingsWithDefaultVerbose()
    {
        $option        = new stdClass();
        $option->value = false;
        $this->input->expects($this->once())->method('getOptionValues');
        $this->input->expects($this->once())->method('getOption')->with($this->equalTo('verbose'))->will($this->returnValue($option));

        $return = $this->shell->loadSettings('');

        $this->assertEquals(false, $this->shell->runtime['Default']['verbose']);
        $this->assertEquals(true, $return);
    }

    /**
     * loadSettings is using verbose from cli option
     */
    public function testLoadSettingsWithVerboseFromOption()
    {
        $option        = new stdClass();
        $option->value = true;
        $this->input->expects($this->never())->method('getOptionValues');
        $this->input->expects($this->any())->method('getOption')->with($this->equalTo('verbose'))->will($this->returnValue($option));

        $return = $this->shell->loadSettings('', ['verbose' => false]);

        $this->assertEquals(true, $this->shell->runtime['Default']['verbose']);
        $this->assertEquals(true, $return);
    }

    /**
     * loadSettings call testConfig when not a test command
     */
    public function testLoadSettingCallForTestConfig()
    {
        $testResults = [
            'name1' => true,
            'name2' => true,
        ];

        $option        = new stdClass();
        $option->value = true;
        $this->input->expects($this->once())->method('getOptionValues');
        $this->input->expects($this->any())->method('getOption')->with($this->equalTo('verbose'))->will($this->returnValue($option));
        $this->shell->expects($this->once())->method('testConfig')->will($this->returnValue($testResults));

        $return = $this->shell->loadSettings('');

        $this->assertEquals(true, $return);
    }

    /**
     * loadSettings call testConfig when not a test command
     */
    public function testLoadSettingDoNotCallForTestConfigOnTestCommand()
    {
        $option        = new stdClass();
        $option->value = true;
        $this->input->expects($this->once())->method('getOptionValues');
        $this->input->expects($this->any())->method('getOption')->with($this->equalTo('verbose'))->will($this->returnValue($option));
        $this->shell->expects($this->never())->method('testConfig');

        $return = $this->shell->loadSettings('test');

        $this->assertEquals(true, $return);
    }

    /**
     * loadSettings die when settings contains errors
     */
    public function testLoadSettingsDieWhenConfigContainsError()
    {
        $errors = [
            'name1' => 'message1',
            'name2' => 'message2',
        ];

        $option        = new stdClass();
        $option->value = true;
        $this->input->expects($this->once())->method('getOptionValues');
        $this->input->expects($this->any())->method('getOption')->with($this->equalTo('verbose'))->will($this->returnValue($option));
        $this->shell->expects($this->once())->method('testConfig')->will($this->returnValue($errors));

        $this->output
            ->expects($this->exactly(3))
            ->method('outputLine')
            ->withConsecutive(
                [$this->equalTo($errors['name1'])],
                [$this->equalTo($errors['name2'])],
            );

        $return = $this->shell->loadSettings('');

        $this->assertEquals(false, $return);
    }

    /**
     * loadSettings will override .ini file settings with cli option
     */
    public function testLoadSettingsOverrideDefaultSettingsWithCLIOption()
    {
        $cliOption     = ['host' => 'testhost', 'include' => 'custom.php'];
        $option        = new stdClass();
        $option->value = false;
        $this->input->expects($this->once())->method('getOptionValues')->will($this->returnValue($cliOption));
        $this->input->expects($this->once())->method('getOption')->with($this->equalTo('verbose'))->will($this->returnValue($option));

        $return = $this->shell->loadSettings('');

        // New settings from CLI
        $this->assertEquals($cliOption['host'], $this->shell->runtime['Redis']['host']);
        $this->assertEquals($cliOption['include'], $this->shell->runtime['Fresque']['include']);

        // Other settings did not change
        $this->assertEquals(6379, $this->shell->runtime['Redis']['port']);

        $this->assertEquals(true, $return);
    }

    /**
     * loadSettings setup Queues for load command
     */
    public function testLoadSettingsSetupQueuesForLoadCommand()
    {
        $config_file = __DIR__ . '/test_fresque.ini';

        $option        = new stdClass();
        $option->value = false;
        $this->input->expects($this->once())->method('getOptionValues')->will($this->returnValue(['config' => $config_file]));
        $this->input->expects($this->once())->method('getOption')->with($this->equalTo('verbose'))->will($this->returnValue($option));

        $return = $this->shell->loadSettings('');

        $config = parse_ini_file($config_file, true);

        $config['Queues']['activity']['queue'] = 'activity';

        $this->assertEquals($config['Queues'], $this->shell->runtime['Queues']);
        $this->assertEquals(true, $return);
    }

}

class DummyWorker
{
    public function __construct($name, $processedStat = 0, $failedStat = 0)
    {
        $this->name          = $name;
        $this->processedStat = $processedStat;
        $this->failedStat    = $failedStat;
    }

    public function getStat($cat)
    {
        switch ($cat) {
            case 'processed' :
                return $this->processedStat;
            case 'failed' :
                return $this->failedStat;
        }
    }

    public function __toString()
    {
        return $this->name;
    }
}
