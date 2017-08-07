<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\AclBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Acl\Dbal\Schema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\SchemaException;

/**
 * Installs the tables required by the ACL system.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @final since version 3.4
 */
class InitAclCommand extends ContainerAwareCommand
{
    private $connection;
    private $schema;

    /**
     * @param Connection $connection
     * @param Schema     $schema
     */
    public function __construct($connection = null, Schema $schema = null)
    {
        if (!$connection instanceof Connection) {
            @trigger_error(sprintf('Passing a command name as the first argument of "%s" is deprecated since version 3.4 and will be removed in 4.0. If the command was registered by convention, make it a service instead.', __METHOD__), E_USER_DEPRECATED);

            parent::__construct($connection);

            return;
        }

        parent::__construct();

        $this->connection = $connection;
        $this->schema = $schema;
    }

    /**
     * {@inheritdoc}
     *
     * BC to be removed in 4.0
     */
    public function isEnabled()
    {
        if (!$this->connection && !$this->getContainer()->has('security.acl.dbal.connection')) {
            return false;
        }

        return parent::isEnabled();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('acl:init')
            ->setAliases(array('init:acl'))
            ->setDescription('Mounts ACL tables in the database')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command mounts ACL tables in the database.

  <info>php %command.full_name%</info>

The name of the DBAL connection must be configured in your <info>app/config/security.yml</info> configuration file in the <info>security.acl.connection</info> variable.

  <info>security:
      acl:
          connection: default</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (false !== strpos($input->getFirstArgument(), ':a')) {
            $warning = 'The use of "init:acl" command is deprecated since version 3.4 and will be removed in 4.0. Use the "acl:init" command instead.';

            @trigger_error($warning, E_USER_DEPRECATED);

            $output->writeln('<comment>'.$warning.'</>');
        }

        // BC to be removed in 4.0
        if (null === $this->connection) {
            $this->connection = $this->getContainer()->get('security.acl.dbal.connection');
            $this->schema = $this->getContainer()->get('security.acl.dbal.schema');
        }

        try {
            $this->schema->addToSchema($this->connection->getSchemaManager()->createSchema());
        } catch (SchemaException $e) {
            $output->writeln('Aborting: '.$e->getMessage());

            return 1;
        }

        foreach ($this->schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->exec($sql);
        }

        $output->writeln('ACL tables have been initialized successfully.');
    }
}
