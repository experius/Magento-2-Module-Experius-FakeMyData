<?php


namespace Experius\FakeMyData\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FakeMyData extends Command
{
    protected $fakeMyDataModel;

    public function __construct(
        \Experius\FakeMyData\Model\FakeMyData $fakeMyDataModel
    ) {
        $this->fakeMyDataModel = $fakeMyDataModel;
        parent::__construct('fakemydata');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $this->fakeMyDataModel->fakeAll();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("experius_fakemydata:fakeall");
        $this->setDescription("Replace Customer Data with Fake Names");
        parent::configure();
    }
}
