<?php

namespace Foolz\FoolFuuka\Model;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use Foolz\FoolFrame\Model\Logger;
use Foolz\FoolFrame\Model\Preferences;
use Foolz\Inet\Inet;
use Foolz\Cache\Cache;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Drivers\Mysqli\Connection as SphinxConnnection;

class SearchException extends \Exception {}
class SearchRequiresSphinxException extends SearchException {}
class SearchSphinxOfflineException extends SearchException {}
class SearchInvalidException extends SearchException {}
class SearchEmptyResultException extends SearchException {}

class Search extends Board
{
    /**
     * The total number of results found
     *
     * @var  int
     */
    protected $total_found;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Preferences
     */
    protected $preferences;

    /**
     * @var RadixCollection
     */
    protected $radix_coll;

    /**
     * @var MediaFactory
     */
    protected $media_factory;

    /**
     * Title for the search query
     *
     * @var string
     */
    public $title;

    public function __construct(\Foolz\FoolFrame\Model\Context $context)
    {
        parent::__construct($context);

        $this->logger = $context->getService('logger');
        $this->preferences = $context->getService('preferences');
        $this->radix_coll = $context->getService('foolfuuka.radix_collection');
        $this->media_factory = $context->getService('foolfuuka.media_factory');
    }

    /**
     * Returns the structure for the search form
     *
     * @return  array
     */
    public static function structure()
    {
        return [
            [
                'type' => 'input',
                'label' => _i('Comment'),
                'name' => 'text'
            ],
            [
                'type' => 'input',
                'label' => _i('Thread No.'),
                'name' => 'tnum'
            ],
            [
                'type' => 'input',
                'label' => _i('Subject'),
                'name' => 'subject'
            ],
            [
                'type' => 'input',
                'label' => _i('Username'),
                'name' => 'username'
            ],
            [
                'type' => 'input',
                'label' => _i('Tripcode'),
                'name' => 'tripcode'
            ],
            [
                'type' => 'input',
                'label' => _i('Email'),
                'name' => 'email'
            ],
            [
                'type' => 'input',
                'label' => _i('Unique ID'),
                'name' => 'uid'
            ],
            [
                'type' => 'input',
                'label' => _i('Since4pass'),
                'placeholder' => '',
                'name' => 'since4pass',
            ],
            [
                'type' => 'input',
                'label' => _i('Country'),
                'name' => 'country'
            ],
            [
                'type' => 'input',
                'label' => _i('Poster IP'),
                'name' => 'poster_ip',
                'access' => 'comment.see_ip'
            ],
            [
                'type' => 'input',
                'label' => _i('Filename'),
                'name' => 'filename'
            ],
            [
                'type' => 'input',
                'label' => _i('Image Hash'),
                'placeholder' => _i('Drop your image here'),
                'name' => 'image'
            ],
            [
                'type' => 'input',
                'label' => _i('Image Width'),
                'placeholder' => '',
                'name' => 'width'
            ],
            [
                'type' => 'input',
                'label' => _i('Image Height'),
                'placeholder' => '',
                'name' => 'height'
            ],
            [
                'type' => 'date',
                'label' => _i('Date Start'),
                'name' => 'start',
                'placeholder' => 'YYYY-MM-DD'
            ],
            [
                'type' => 'date',
                'label' => _i('Date End'),
                'name' => 'end',
                'placeholder' => 'YYYY-MM-DD'
            ],
            [
                'type' => 'radio',
                'label' => _i('Capcode'),
                'name' => 'capcode',
                'elements' => [
                    ['value' => false, 'text' => _i('All')],
                    ['value' => 'user', 'text' => _i('Only User Posts')],
                    ['value' => 'ver', 'text' => _i('Only Verified Posts')],
                    ['value' => 'mod', 'text' => _i('Only Moderator Posts')],
                    ['value' => 'manager', 'text' => _i('Only Manager Posts')],
                    ['value' => 'admin', 'text' => _i('Only Admin Posts')],
                    ['value' => 'dev', 'text' => _i('Only Developer Posts')],
                    ['value' => 'founder', 'text' => _i('Only Founder Posts')]
                ]
            ],
            [
                'type' => 'radio',
                'label' => _i('Show Posts'),
                'name' => 'filter',
                'elements' => [
                    ['value' => false, 'text' => _i('All')],
                    ['value' => 'text', 'text' => _i('Only With Images')],
                    ['value' => 'image', 'text' => _i('Only Without Images')],
                    ['value' => 'spoiler', 'text' => _i('Only Spoiler Images')],
                    ['value' => 'not-spoiler', 'text' => _i('Only Non-Spoiler Images')]
                ]
            ],
            [
                'type' => 'radio',
                'label' => _i('Deleted Posts'),
                'name' => 'deleted',
                'elements' => [
                    ['value' => false, 'text' => _i('All')],
                    ['value' => 'deleted', 'text' => _i('Only Deleted Posts')],
                    ['value' => 'not-deleted', 'text' => _i('Only Non-Deleted Posts')]
                ]
            ],
            [
                'type' => 'radio',
                'label' => _i('Ghost Posts'),
                'name' => 'ghost',
                'elements' => [
                    ['value' => false, 'text' => _i('All')],
                    ['value' => 'only', 'text' => _i('Only Ghost Posts')],
                    ['value' => 'none', 'text' => _i('Only Non-Ghost Posts')]
                ]
            ],
            [
                'type' => 'radio',
                'label' => _i('Post Type'),
                'name' => 'type',
                'elements' => [
                    ['value' => false, 'text' => _i('All')],
                    ['value' => 'sticky', 'text' => _i('Only Sticky Threads')],
                    ['value' => 'op', 'text' => _i('Only Opening Posts')],
                    ['value' => 'posts', 'text' => _i('Only Reply Posts')]
                ]
            ],
            [
                'type' => 'radio',
                'label' => _i('Results'),
                'name' => 'results',
                'elements' => [
                    ['value' => false, 'text' => _i('All')],
                    ['value' => 'thread', 'text' => _i('Grouped By Threads')]
                ]
            ],
            [
                'type' => 'radio',
                'label' => _i('Order'),
                'name' => 'order',
                'elements' => [
                    ['value' => false, 'text' => _i('Latest Posts First')],
                    ['value' => 'asc', 'text' => _i('Oldest Posts First')]
                ]
            ]
        ];
    }

    /**
     * Sets the Board to Search mode
     * Options: (array)arguments, (int)limit, (int)page
     *
     * @param  array  $arguments  The search arguments
     *
     * @return  \Foolz\FoolFuuka\Model\Search  The current object
     */
    protected function p_getSearch($arguments)
    {
        // prepare
        $this->setMethodFetching('getResults')
            ->setMethodCounting('getTotalResults')
            ->setOptions([
                'args' => $arguments,
                'limit' => 25,
            ]);

        return $this;
    }

    protected function getUserInput()
    {
        $args = [];
        extract($this->options);

        $search_inputs = [
            'boards', 'subject', 'text', 'tnum', 'username', 'tripcode', 'email', 'capcode', 'uid', 'poster_ip', 'country',
            'filename', 'image', 'deleted', 'ghost', 'filter', 'type', 'start', 'end', 'results', 'order', 'since4pass', 'width', 'height'
        ];

        foreach ($search_inputs as $field) {
            if (!isset($args[$field])) {
                $args[$field] = null;
            }
        }

        return $args;
    }

    /**
     * Gets the search results
     *
     * @return  \Foolz\FoolFuuka\Model\Search  The current object
     * @throws  SearchEmptyResultException     If there's no results to display
     * @throws  SearchRequiresSphinxException  If the search submitted requires Sphinx to run
     * @throws  SearchSphinxOfflineException   If the Sphinx server is unreachable
     * @throws  SearchInvalidException         If the values of the search weren't compatible with the domain
     */
    protected function p_getResults()
    {
        $this->profiler->log('Search::getResults Start');
        extract($this->options);

        $boards = [];
        $input = $this->getUserInput();

        if ($this->radix !== null) {
            $boards[] = $this->radix;
        } elseif ($input['boards'] !== null) {
            foreach ($input['boards'] as $board) {
                $b = $this->radix_coll->getByShortname($board);
                if ($b) {
                    $boards[] = $b;
                }
            }
        }

        // search all boards if none selected
        if (count($boards) == 0) {
            $boards = $this->radix_coll->getAll();
        }

        // if image is set, get either the media_hash or media_id
        if ($input['image'] !== null && substr($input['image'], -2) !== '==') {
            $input['image'] .= '==';
        }

        if ($this->radix === null && !$this->preferences->get('foolfuuka.sphinx.global')) {
            throw new SearchRequiresSphinxException(_i('Sorry, the global search function has not been enabled.'));
        }

        if ($this->radix !== null && !$this->radix->sphinx) {
            throw new SearchRequiresSphinxException(_i('Sorry, this board does not have search enabled.'));
        }

        $results = [];

        try {
            if (!$this->preferences->get('foolfuuka.sphinx.enable_cache')) {
                throw new \OutOfBoundsException;
            }
            $cache = Cache::item('foolfuuka.model.search.getResults.'
                . md5(serialize([($this->radix ? $this->radix->shortname : '_'), $input])))->get();
            $results = $cache['res'];
            $this->total_count = $cache['total'];
            $this->total_found = $cache['found'];
            unset($cache);
        } catch (\OutOfBoundsException $e) {
            // $sphinx = explode(':', $this->preferences->get('foolfuuka.sphinx.listen'));
            // $conn = new SphinxConnnection();
            // $conn->setParams([
            //     'host' => $sphinx[0],
            //     'port' => $sphinx[1],
            //     'options' => [MYSQLI_OPT_CONNECT_TIMEOUT => 5]
            //]);
            $config = new Configuration();
            // $config->setSQLLogger(new \Foolz\FoolFrame\Model\DoctrineLogger($context));
            $data = [
                'url' => $this->preferences->get('foolfuuka.sphinx.listen')
            ];
            $conn = DriverManager::getConnection($data, $config);

            $indices = [];
            foreach ($boards as $radix) {
                if (!$radix->sphinx) {
                    continue;
                }

                $indices[] = $radix->shortname . '_ancient';
                $indices[] = $radix->shortname . '_main';
                $indices[] = $radix->shortname . '_delta';
            }

            $query = $conn->createQueryBuilder()
                    ->select('board', 'thread_no', 'post_no')
                    ->from('posts');

            $btx = function($value) {
                return $value->shortname;
            };
            $board_ids = array_map($btx, $boards);
            $query->where('board IN (:boards)')
                ->setParameter('boards', $board_ids, Connection::PARAM_STR_ARRAY);

            // process user input
            if ($input['subject'] !== null) {
                // $query->match('title', $input['subject']);
                $query->andWhere('subject @@ websearch_to_tsquery(:subject)');
                $query->setParameter('subject', $input['subject']);
            }

            if ($input['text'] !== null) {
                if (mb_strlen($input['text'], 'utf-8') < 1) {
                    return [];
                }

                // $query->match('comment', $input['text'], true);
                $query->andWhere('comment @@ websearch_to_tsquery(:text)');
                $query->setParameter('text', $input['text']);
            }

            if ($input['tnum'] !== null) {
                // $query->where('tnum', (int)$input['tnum']);
                $query->andWhere('thread_no = :tnum');
                $query->setParameter('tnum', $input['tnum']);
            }

            if ($input['username'] !== null) {
                // $query->match('name', $input['username']);
                $query->andWhere('username @@ websearch_to_tsquery(:username)');
                $query->setParameter('username', $input['username']);
            }

            if ($input['tripcode'] !== null) {
                // $query->match('trip', '"' . $input['tripcode'] . '"');
                $query->andWhere('tripcode @@ websearch_to_tsquery(:tripcode)');
                $query->setParameter('tripcode', $input['tripcode']);
            }

            if ($input['email'] !== null) {
                // $query->match('email', $input['email']);
                $query->andWhere('email @@ websearch_to_tsquery(:email)');
                $query->setParameter('email', $input['email']);
            }

            if ($input['since4pass'] !== null) {
                $query->andWhere('since4_pass = :since4pass');
                $query->setParameter('since4pass', $input['since4pass']);
            }

            if ($input['capcode'] !== null) {
                switch ($input['capcode']) {
                    case 'user':
                        $query->andWhere('cap = '.ord('N'));
                        break;
                    case 'mod':
                        $query->andWhere('cap = '.ord('M'));
                        break;
                    case 'dev':
                        $query->andWhere('cap = '.ord('D'));
                        break;
                    case 'admin':
                        $query->andWhere('cap = '.ord('A'));
                        break;
                    case 'ver':
                        $query->andWhere('cap = '.ord('V'));
                        break;
                    case 'founder':
                        $query->andWhere('cap = '.ord('F'));
                        break;
                    case 'manager':
                        $query->andWhere('cap = '.ord('G'));
                        break;
                }
            }

            if ($input['uid'] !== null) {
                //$query->match('pid', $input['uid']);
                $query->andWhere('unique_id = :uid');
                $query->setParameter('uid', $input['uid']);
            }

            if ($input['country'] !== null) {
                // $query->match('country', $input['country'], true);
                $query->andWhere('country = :country');
                $query->setParameter('country', $input['country']);
            }

            if ($this->getAuth()->hasAccess('comment.see_ip') && $input['poster_ip'] !== null) {
                throw new SearchInvalidException(_i('Search by IP address is not supported.'));
                //$query->where('pip', (int)Inet::ptod($input['poster_ip']));
            }

            if ($input['filename'] !== null) {
                // $query->match('media_filename', $input['filename']);
                $query->andWhere('filename @@ websearch_to_tsquery(:filename)');
                $query->setParameter('filename', str_replace(".", " ", $input['filename']));
            }

            if ($input['image'] !== null) {
                // $query->match('media_hash', '"' . $input['image'] . '"');
                $query->andWhere('image_hash = :media_hash');
                $query->setParameter('media_hash', $input['media_hash']);
            }

            if ($input['width'] !== null) {
                $query->andWhere('image_width = :width');
                $query->setParameter('width', (int)$input['width']);
            }

            if ($input['height'] !== null) {
                $query->andWhere('image_height = :height');
                $query->setParameter('height', (int)$input['height']);
            }

            if ($input['deleted'] !== null) {
                switch ($input['deleted']) {
                    case 'deleted':
                        $query->andWhere('deleted = TRUE');
                        break;
                    case 'not-deleted':
                        $query->andWhere('deleted = FALSE');
                        break;
                }
            }

            if ($input['ghost'] !== null) {
                switch ($input['ghost']) {
                    case 'only':
                        $query->andWhere('ghost = TRUE');
                        break;
                    case 'none':
                        $query->andWhere('ghost = FALSE');
                        break;
                }
            }


            if ($input['filter'] !== null) {
                switch ($input['filter']) {
                    case 'image':
                        $query->andWhere('image_width = 0');
                        break;
                    case 'text':
                        $query->andWhere('image_width != 0');
                        break;
                    case 'spoiler':
                        $query->andWhere('image_width != 0');
                        $query->andWhere('spoiler = TRUE');
                        break;
                    case 'not-spoiler':
                        $query->andWhere('image_width != 0');
                        $query->andWhere('spoiler = FALSE');
                        break;
                }
            }

            if ($input['type'] !== null) {
                switch ($input['type']) {
                    case 'sticky':
                        $query->andWhere('sticky = TRUE');
                        break;
                    case 'op':
                        $query->andWhere('op = TRUE');
                        break;
                    case 'posts':
                        $query->andWhere('op = FALSE');
                        break;
                }
            }

            if ($input['start'] !== null) {
                $query->andWhere('ts >= TO_TIMESTAMP(:start)');
                $query->setParameter('start', floatval(strtotime($input['start'])) );
            }

            if ($input['end'] !== null) {
                $query->andWhere('ts <= TO_TIMESTAMP(:end)');
                $query->setParameter('end', floatval(strtotime($input['end'])));
            }

            // if ($input['results'] !== null && $input['results'] == 'thread') {
            //     $query->groupBy('tnum');
            //    $query->withinGroupOrderBy('is_op', 'desc');
            //}

            if ($input['order'] !== null && $input['order'] == 'asc') {
                $query->orderBy('ts', 'ASC');
            } else {
                $query->orderBy('ts', 'DESC');
            }

            $max_matches = (int)$this->preferences->get('foolfuuka.sphinx.max_matches', 5000);
            $first_result = (($page * $limit) - $limit) >= $max_matches ? ($max_matches - 1) : ($page * $limit) - $limit;

            // set sphinx options
            $query->setMaxResults($max_matches - $first_result)
                ->setFirstResult($first_result);
                // ->option('max_matches', (int)$max_matches)
                // ->option('reverse_scan', ($input['order'] === 'asc') ? 0 : 1);

            // submit query
             //echo($query->getSql());
            try {
                $this->profiler->log('Start: SearchQL');
                $search = $query->execute()->fetchAll();
                $this->profiler->log('Stop: SearchQL');
            } catch (\Foolz\SphinxQL\Exception\DatabaseException $e) {
                $this->logger->error('Search Error: ' . $e->getMessage());
                throw new SearchInvalidException(_i('The search backend returned an error.'));
            } catch (\Exception $e) {
                $this->logger->error('Search Error: ' . $e->getMessage());
                throw new SearchSphinxOfflineException($this->preferences->get('foolfuuka.sphinx.custom_message', _i('The search backend is currently unavailable.')));
            }

            // no results found
            if (!count($search)) {
                $this->comments_unsorted = [];
                $this->comments = [];

                throw new SearchEmptyResultException(_i('No results found.'));
            }

            // $sphinx_meta = Helper::pairsToAssoc((new Helper($conn))->showMeta()->execute());
            $this->total_count = count($search) + $first_result; //$sphinx_meta['total'];
            $this->total_found = count($search) + $first_result; //$sphinx_meta['total_found'];
            $search = array_slice($search, 0, $limit);

            foreach ($search as $doc => $result) {

                $board = $this->radix_coll->getByShortname($result['board']);
                if ($board->getValue('external_database')) {
                    $newdc = new BoardConnection($this->getContext(), $board);
                } else {
                    $newdc = $this->dc;
                }


                if ($input['results'] !== null && $input['results'] == 'thread') {
                    $post = 'num = ' . $this->dc->getConnection()->quote($result['thread_no']) . ' AND subnum = 0';
                } else {
                    $post = 'num = ' . $this->dc->getConnection()->quote($result['post_no']) . ' AND subnum = 0';
                }



                $sql = $newdc->qb()
                    ->select('*, ' . $board->id . ' AS board_id')
                    ->from($board->getTable(), 'r')
                    ->leftJoin('r', $board->getTable('_images'), 'mg', 'mg.media_id = r.media_id')
                    ->where($post)
                    ->getSQL();

                foreach ($newdc->getConnection()
                             ->executeQuery($sql)
                             ->fetchAll() as $res) {
                    $results[] = $res;
                }
            }

            if ($this->preferences->get('foolfuuka.sphinx.enable_cache')) {
                Cache::item('foolfuuka.model.search.getResults.'
                    . md5(serialize([($this->radix ? $this->radix->shortname : '_'), $input])))->set(['res' => $results, 'total' => $this->total_count, 'found' => $this->total_found],
                    $this->preferences->get('foolfuuka.sphinx.cache_timeout'));
            }
        }

        // no results found IN DATABASE, but we might still get a search count from Sphinx
        if (!$results) {
            $this->comments_unsorted = [];
            $this->comments = [];
        } else {
            foreach ($results as $row) {
                $board = ($this->radix !== null ? $this->radix : $this->radix_coll->getById($row['board_id']));
                $bulk = new CommentBulk();
                $bulk->import($row, $board);
                $this->comments_unsorted[] = $bulk;
            }
        }

        $this->comments[0]['posts'] = $this->comments_unsorted;
        $this->title = $this->buildSearchTitle($input);

        return $this;
    }

    /**
     * Returns the total number of results found WITHOUT max_matches.
     *
     * @return  int
     */
    public function getTotalResults()
    {
        return $this->total_found;
    }

    /**
     * Generate the $title with all search modifiers enabled.
     *
     * @param array  $search  The search arguments
     * @return string  Title of search query
     */
    protected function buildSearchTitle($search)
    {
        $title = [];
        if ($search['text'])
            array_push($title,
                sprintf(_i('that contain &lsquo;%s&rsquo;'),
                    e($search['text'])));
        if ($search['tnum'])
            array_push($title,
                sprintf(_i('in thread #%s'),
                    e($search['tnum'])));
        if ($search['subject'])
            array_push($title,
                sprintf(_i('with the subject &lsquo;%s&rsquo;'),
                    e($search['subject'])));
        if ($search['username'])
            array_push($title,
                sprintf(_i('with the username &lsquo;%s&rsquo;'),
                    e($search['username'])));
        if ($search['tripcode'])
            array_push($title,
                sprintf(_i('with the tripcode &lsquo;%s&rsquo;'),
                    e($search['tripcode'])));
        if ($search['uid'])
            array_push($title,
                sprintf(_i('with the unique id &lsquo;%s&rsquo;'),
                    e($search['uid'])));
        if ($search['email'])
            array_push($title,
                sprintf(_i('with the email &lsquo;%s&rsquo;'),
                    e($search['email'])));
        if ($search['filename'])
            array_push($title,
                sprintf(_i('with the filename &lsquo;%s&rsquo;'),
                    e($search['filename'])));
        if ($search['image']) {
            array_push($title,
                sprintf(_i('with the image hash &lsquo;%s&rsquo;'),
                    e($search['image'])));
        }
        if ($search['width']) {
            array_push($title,
                sprintf(_i('with the image width &lsquo;%s&rsquo;'),
                    e($search['width'])));
        }
        if ($search['height']) {
            array_push($title,
                sprintf(_i('with the image height &lsquo;%s&rsquo;'),
                    e($search['height'])));
        }
        if ($search['since4pass']) {
            array_push($title,
                sprintf(_i('with since4pass &lsquo;%s&rsquo;'),
                    e($search['since4pass'])));
        }
        if ($search['country'])
            array_push($title,
                sprintf(_i('in &lsquo;%s&rsquo;'),
                    e($search['country'])));
        if ($search['deleted'] == 'deleted')
            array_push($title, _i('that have been deleted'));
        if ($search['deleted'] == 'not-deleted')
            array_push($title, _i('that has not been deleted'));
        if ($search['ghost'] == 'only')
            array_push($title, _i('that are by ghosts'));
        if ($search['ghost'] == 'none')
            array_push($title, _i('that are not by ghosts'));
        if ($search['type'] == 'sticky')
            array_push($title, _i('that were stickied'));
        if ($search['type'] == 'op')
            array_push($title, _i('that are only OP posts'));
        if ($search['type'] == 'posts')
            array_push($title, _i('that are only non-OP posts'));
        if ($search['filter'] == 'image')
            array_push($title, _i('that do not contain images'));
        if ($search['filter'] == 'text')
            array_push($title, _i('that only contain images'));
        if ($search['filter'] == 'spoiler')
            array_push($title, _i('that only contain spoiler images'));
        if ($search['filter'] == 'not-spoiler')
            array_push($title, _i('that do not contain spoiler images'));
        if ($search['capcode'] == 'user')
            array_push($title, _i('that were made by users'));
        if ($search['capcode'] == 'mod')
            array_push($title, _i('that were made by mods'));
        if ($search['capcode'] == 'admin')
            array_push($title, _i('that were made by admins'));
        if ($search['capcode'] == 'ver')
            array_push($title, _i('that were made by verified users'));
        if ($search['capcode'] == 'founder')
            array_push($title, _i('that were made by founders'));
        if ($search['capcode'] == 'manager')
            array_push($title, _i('that were made by managers'));
        if ($search['start'])
            array_push($title, sprintf(_i('posts after %s'), e($search['start'])));
        if ($search['end'])
            array_push($title, sprintf(_i('posts before %s'), e($search['end'])));
        if ($search['order'] == 'asc')
            array_push($title, _i('in ascending order'));

        if (!empty($title)) {
            $title = sprintf(_i('Searching for posts %s.'),
                implode(' ' . _i('and') . ' ', $title));
        } else {
            $title = _i('Displaying all posts with no filters applied.');
        }

        return $title;
    }
}
