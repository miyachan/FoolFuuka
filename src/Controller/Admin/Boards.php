<?php

namespace Foolz\FoolFuuka\Controller\Admin;

use Foolz\FoolFrame\Model\DoctrineConnection;
use Foolz\FoolFrame\Model\Validation\ActiveConstraint\Trim;
use Foolz\FoolFrame\Model\Validation\Validator;
use Foolz\FoolFuuka\Model\RadixCollection;
use Foolz\Theme\Loader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Constraints as Assert;


class Boards extends \Foolz\FoolFrame\Controller\Admin
{
    /**
     * @var RadixCollection
     */
    protected $radix_coll;

    public function before()
    {
        parent::before();

        $this->radix_coll = $this->getContext()->getService('foolfuuka.radix_collection');

        $this->param_manager->setParam('controller_title', _i('Boards'));
    }

    public function security()
    {
        return $this->getAuth()->hasAccess('boards.edit');
    }

    /**
     * Selects the theme. Can be overridden so other controllers can use their own admin components
     *
     * @param Loader $theme_instance
     */
    public function setupTheme(Loader $theme_instance)
    {
        // we need to load more themes
        $theme_instance->addDir(ASSETSPATH.'themes-admin');
        $this->theme = $theme_instance->get('foolz/foolfuuka-theme-admin');
    }

    public function action_manage()
    {
        $this->param_manager->setParam('method_title', _i('Manage'));
        $this->builder->createPartial('body', 'boards/manage')
            ->getParamManager()->setParam('boards', $this->radix_coll->getAll());

        return new Response($this->builder->build());
    }

    public function action_board($shortname = null)
    {
        $data['form'] = $this->radix_coll->structure();

        if ($this->getPost() && !$this->checkCsrfToken()) {
            $this->notices->set('warning', _i('The security token was not found. Please try again.'));
        } elseif ($this->getPost()) {
            $result = Validator::formValidate($data['form'], $this->getPost());

            if (isset($result['error'])) {
                $this->notices->set('warning', $result['error']);
            } else {
                // it's actually fully checked, we just have to throw it in DB
                $this->radix_coll->save($result['success']);

                if (is_null($shortname)) {
                    $this->notices->setFlash('success', _i('New board created!'));
                    return $this->redirect('admin/boards/board/'.$result['success']['shortname']);
                } elseif ($shortname != $result['success']['shortname']) {
                    // case in which letter was changed
                    $this->notices->setFlash('success', _i('Board information updated.'));
                    return $this->redirect('admin/boards/board/'.$result['success']['shortname']);
                } else {
                    $this->notices->set('success', _i('Board information updated.'));
                }
            }
        }

        $board = $this->radix_coll->getByShortname($shortname);
        if ($board === false) {
            throw new NotFoundHttpException;
        }

        $data['object'] = (object) $board->getAllValues();

        $this->param_manager->setParam('method_title', [_i('Manage'), _i('Edit'), $shortname]);
        $this->builder->createPartial('body', 'form_creator')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }

    function action_add()
    {
        $data['form'] = $this->radix_coll->structure();

        if ($this->getPost() && !$this->checkCsrfToken()) {
            $this->notices->set('warning', _i('The security token wasn\'t found. Try resubmitting.'));
        } elseif ($this->getPost()) {
            $result = Validator::formValidate($data['form'], $this->getPost());
            if (isset($result['error'])) {
                $this->notices->set('warning', $result['error']);
            } else {
                // it's actually fully checked, we just have to throw it in DB
                $this->radix_coll->save($result['success']);
                $this->notices->setFlash('success', _i('New board created!'));
                return $this->redirect('admin/boards/board/'.$result['success']['shortname']);
            }
        }

        // the actual POST is in the board() function
        $data['form']['open']['action'] = $this->uri->create('admin/boards/add_new');

        // panel for creating a new board
        $this->param_manager->setParam('method_title', [_i('Manage'), _i('Add')]);
        $this->builder->createPartial('body', 'form_creator')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }

    function action_delete($id = 0)
    {
        $board = $this->radix_coll->getById($id);
        if ($board == false) {
            throw new NotFoundHttpException;
        }

        if ($this->getPost() && !$this->checkCsrfToken()) {
            $this->notices->set('warning', _i('The security token wasn\'t found. Try resubmitting.'));
        } elseif ($this->getPost()) {
            $board->remove($id);
            $this->notices->setFlash('success', sprintf(_i('The board %s has been deleted.'), $board->shortname));
            return $this->redirect('admin/boards/manage');
        }

        $data['alert_level'] = 'warning';
        $data['message'] = _i('Do you really want to remove the board and all its data?').
            '<br/>'.
            _i('Notice: due to its size, you will have to remove the image directory manually. The directory will have the "_removed" suffix. You can remove all the leftover "_removed" directories with the following command:').
            ' <code>php index.php cli boards remove_leftover_dirs</code>';

        $this->param_manager->setParam('method_title', _i('Removing board:').' '.$board->shortname);
        $this->builder->createPartial('body', 'confirm')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }

    function action_preferences()
    {
        /** @var DoctrineConnection $dc */
        $dc = $this->getContext()->getService('doctrine');

        $form = [];

        $form['open'] = [
            'type' => 'open'
        ];

        $form['foolfuuka.boards.directory'] = [
            'type' => 'input',
            'label' => _i('Boards directory'),
            'preferences' => true,
            'help' => _i('Overrides the default path to the boards directory (Example: /var/www/foolfuuka/boards)')
        ];

        $form['foolfuuka.boards.url'] = [
            'type' => 'input',
            'label' => _i('Boards URL'),
            'preferences' => true,
            'help' => _i('Overrides the default url to the boards folder (Example: http://foolfuuka.site.com/there/boards)')
        ];

        if ($dc->getConnection()->getDriver()->getName() != 'pdo_pgsql') {
            $form['foolfuuka.boards.db'] = [
                'type' => 'input',
                'label' => _i('Boards database'),
                'preferences' => true,
                'help' => _i('Overrides the default database. You should point it to your Asagi database if you have a separate one.')
            ];

            $form['foolfuuka.boards.prefix'] = [
                'type' => 'input',
                'label' => _i('Boards prefix'),
                'preferences' => true,
                'help' => _i('Overrides the default prefix (which would be "'.$dc->p('').'board_"). Asagi doesn\'t use a prefix by default.')
            ];

            // it REALLY must never have been set
            if ($this->preferences->get('foolfuuka.boards.prefix', null, true) === null) {
                $form['foolfuuka.boards.prefix']['value'] = $dc->p('').'board_';
            }
        }

        $form['foolfuuka.boards.media_balancers'] = [
            'type' => 'textarea',
            'label' => _i('Media load balancers'),
            'preferences' => true,
            'help' => _i('Facultative. One per line the URLs where your images are reachable.'),
            'class' => 'span6'
        ];

        $form['foolfuuka.boards.media_balancers_https'] = [
            'type' => 'textarea',
            'label' => _i('HTTPS media load balancers'),
            'preferences' => true,
            'help' => _i('Facultative. One per line the URLs where your images are reachable. This is used when the site is loaded via HTTPS protocol, and if empty it will fall back to HTTP media load balancers.'),
            'class' => 'span6'
        ];

        $form['foolfuuka.boards.media_download_url'] = [
            'type' => 'input',
            'label' => _i('Boards Media Download URL'),
            'preferences' => true,
        ];

        $form['separator-2'] = [
            'type' => 'separator'
        ];

        $form['paragraph2'] = array(
            'type' => 'paragraph',
            'help' => _i('In order to use reCAPTCHA2&trade; you need to sign up for the service at <a href="http://www.google.com/recaptcha">reCAPTCHA2&trade;</a>, which will provide you with a site and a secret key. If these are set reCAPTCHA2&trade; will be prefered.')
        );

        $form['foolframe.auth.recaptcha2_sitekey'] = array(
            'type' => 'input',
            'label' => _i('reCaptcha2&trade; Site Key'),
            'preferences' => true,
            'help' => _i('Insert the Site key provided by reCAPTCHA2&trade;.'),
            'validation' => [new Trim()],
            'class' => 'span4'
        );

        $form['foolframe.auth.recaptcha2_secret'] = array(
            'type' => 'input',
            'label' => _i('reCaptcha2&trade; Secret Key'),
            'preferences' => true,
            'help' => _i('Insert the Secret key provided by reCAPTCHA2&trade;.'),
            'validation' => [new Trim()],
            'class' => 'span4'
        );

        $form['foolfuuka.boards.enable_archive_cache'] = [
            'type' => 'checkbox',
            'label' => _i('Enable archive page caching'),
            'placeholder' => '',
            'preferences' => true,
            'help' => _i('Enable archive page caching'),
            'sub' => [
                'foolfuuka.boards.page_cache_timeout' => [
                    'preferences' => true,
                    'type' => 'input',
                    'label' => _i('Cache timeout (in seconds).'),
                    'placeholder' => '',
                    'validation' => [new Assert\Length(['max' => 256])]
                ],
            ]
        ];

        $form['separator-3'] = [
            'type' => 'separator'
        ];

        $form['submit'] = [
            'type' => 'submit',
            'value' => _i('Submit'),
            'class' => 'btn btn-primary'
        ];

        $form['close'] = [
            'type' => 'close'
        ];

        $this->preferences->submit_auto($this->getRequest(), $form, $this->getPost());

        $data['form'] = $form;

        // create a form
        $this->param_manager->setParam('method_title', _i('Preferences'));
        $this->builder->createPartial('body', 'form_creator')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }

    function action_search()
    {
        $this->_views['method_title'] = _i('Search');

        $form = [];

        $form['open'] = [
            'type' => 'open'
        ];

        $form['foolfuuka.sphinx.global'] = [
            'type' => 'checkbox',
            'label' => 'Global Postgres Search',
            'placeholder' => 'FoolFuuka',
            'preferences' => true,
            'help' => _i('Activate PG Search globally (enables crossboard search)')
        ];

        $form['foolfuuka.sphinx.listen'] = [
            'type' => 'input',
            'label' => 'Database URL',
            'preferences' => true,
            'help' => _i('Set this to the database url of your postgres instance. Must be in form pgsql://user:pass@host/db_name'),
            'class' => 'span6',
            'validation' => [new Trim(), new Assert\Length(['max' => 255])],
            'validation_func' => function($input, $form) {
                    $parsed = parse_url($input['foolfuuka.sphinx.listen']);
                    if (!$parsed) {
                        return [
                            'error_code' => 'INVALID_URL',
                            'error' => _i('The Database URL is invalid.')
                        ];
                    }
                    if (!in_array($parsed["scheme"], array('pgsql', 'postgres', 'postgresql', 'pdo_pgsql'))) {
                        return [
                            'error_code' => 'INVALID_SCHEME',
                            'error' => _i('The database scheme must be pgsql://.')
                        ];
                    }
                    if (!$parsed["path"]) {
                        return [
                            'error_code' => 'INVALID_DB_NAME',
                            'error' => _i('The database name in the URL is invalid.')
                        ];
                    }
                    /*
                    \Foolz\Sphinxql\Sphinxql::addConnection('default', $sphinx_ip_port[0], $sphinx_ip_port[1]);

                    try {
                        \Foolz\Sphinxql\Sphinxql::connect(true);
                    } catch (\Foolz\Sphinxql\SphinxqlConnectionException $e) {
                        return [
                            'warning_code' => 'CONNECTION_NOT_ESTABLISHED',
                            'warning' => _i('The Sphinx server couldn\'t be contacted at the specified address and port.')
                        ];
                    }
                    */
                    return ['success' => true];
                }
        ];

        $form['foolfuuka.sphinx.max_matches'] = [
            'type' => 'input',
            'label' => 'Max Matches',
            'placeholder' => 5000,
            'validation' => [new Trim()],
            'preferences' => true,
            'help' => _i('Set the maximum amount of matches the search daemon keeps in RAM for each index and results returned to the client.'),
            'class' => 'span1'
        ];

        $form['foolfuuka.sphinx.custom_message'] = [
            'type' => 'textarea',
            'label' => 'Custom Error Message',
            'preferences' => true,
            'help' => _i('Set a custom error message.'),
            'class' => 'span6'
        ];

        $form['foolfuuka.sphinx.enable_cache'] = [
            'type' => 'checkbox',
            'label' => _i('Enable search result caching'),
            'placeholder' => '',
            'preferences' => true,
            'help' => _i('Enable search result caching'),
            'sub' => [
                'foolfuuka.sphinx.cache_timeout' => [
                    'preferences' => true,
                    'type' => 'input',
                    'label' => _i('Cache timeout (in seconds). This shouldn\'t be larger than delta index interval'),
                    'placeholder' => '',
                    'validation' => [new Assert\Length(['max' => 256])]
                ],
            ]
        ];

        $form['separator'] = [
            'type' => 'separator'
        ];

        $form['submit'] = [
            'type' => 'submit',
            'value' => _i('Save'),
            'class' => 'btn btn-primary'
        ];

        $form['close'] = [
            'type' => 'close'
        ];

        $this->preferences->submit_auto($this->getRequest(), $form, $this->getPost());

        // create the form
        $data['form'] = $form;

        $this->param_manager->setParam('method_title', _i('Preferences'));
        $partial = $this->builder->createPartial('body', 'form_creator');
        $partial->getParamManager()->setParams($data);
        $built = $partial->build();
        $partial->setBuilt($built.'<a href="'.$this->uri->create('admin/boards/sphinx_config').'" class="btn">'._i('Generate Config').'</a>');

        return new Response($this->builder->build());
    }

    public function action_sphinx_config()
    {
        $data = [];

        $mysql = $this->preferences->get('foolfuuka.sphinx.listen_mysql', null);
        $data['mysql'] = [
            'host' => $mysql === null ? '127.0.0.1' : explode(':', $mysql)[0],
            'port' => $mysql === null ? '3306' : explode(':', $mysql)[1],
            'flag' => $this->preferences->get('foolfuuka.sphinx.connection_flags', '0')
        ];

        $sphinx = $this->preferences->get('foolfuuka.sphinx.listen', null);
        $data['sphinx'] = [
            'port' => $sphinx === null ? '9306' : explode(':', $sphinx)[1],
            'working_directory' => $this->preferences->get('foolfuuka.sphinx.dir', '/usr/local/sphinx'),
            'mem_limit' => $this->preferences->get('foolfuuka.sphinx.mem_limit', '1024M'),
            'min_word_len' => $this->preferences->get('foolfuuka.sphinx.min_word_len', 1),
            'max_children' => $this->preferences->get('foolfuuka.sphinx.max_children', 0),
            'max_matches' => $this->preferences->get('foolfuuka.sphinx.max_matches', 5000),
            'distributed' => (int) $this->preferences->get('foolfuuka.sphinx.distributed', 0)
        ];

        $data['boards'] = $this->radix_coll->getAll();
        $data['example'] = current($data['boards']);

        $this->param_manager->setParam('method_title', [_i('Search'), 'Sphinx', _i('Configuration File'), _i('Generate')]);
        $this->builder->createPartial('body', ($data['sphinx']['distributed'] > 1) ? 'boards/sphinx_dist_config' : 'boards/sphinx_config')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }
}
