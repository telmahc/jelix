<?php
/**
* @package     jelix-scripts
* @author      Laurent Jouanneau
* @copyright   2010-2016 Laurent Jouanneau
* @link        http://jelix.org
* @licence     GNU General Public Licence see LICENCE file or http://www.gnu.org/licenses/gpl.html
*/
namespace Jelix\DevHelper\Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class OpenApp extends \Jelix\DevHelper\AbstractCommand {

    protected function configure()
    {
        $this
            ->setName('app:open')
            ->setDescription('Open the application. It will be accessible from the web.')
            ->setHelp('')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        \Jelix\Core\AppManager::open();
        if ($output->isVerbose()) {
            $output->writeln("Application is opened.");
        }
    }
}
