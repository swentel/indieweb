<?php

namespace Drupal\indieweb_microsub\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\indieweb_microsub\Entity\MicrosubChannelInterface;
use Drupal\indieweb_microsub\Entity\MicrosubSourceInterface;
use p3k\XRay;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class MicrosubController extends ControllerBase {

  /**
   * @var  \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request $request
   */
  protected $request;

  /**
   * @var \Drupal\indieweb_indieauth\IndieAuthClient\IndieAuthClientInterface
   */
  protected $indieAuth;

  /**
   * Microsub endpoint.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function endpoint(Request $request) {

    $this->request = $request;
    $this->indieAuth = \Drupal::service('indieweb.indieauth.client');

    $this->config = \Drupal::config('indieweb_microsub.settings');
    $microsub_enabled = $this->config->get('microsub_internal');

    // Early response when endpoint is not enabled.
    if (!$microsub_enabled) {
      return new JsonResponse('', 404);
    }

    // Default response code and message.
    $response = [
      'message' => 'Bad request',
      'code' => 400,
    ];

    // Get authorization header, response early if none found.
    $auth_header = $this->indieAuth->getAuthorizationHeader();
    if (!$auth_header) {
      return new JsonResponse('', 401);
    }

    // Determine scope.
    $scope = NULL;
    $request_method = $request->getMethod();
    $action = $request->get('action');

    if ($action == 'channels' && $request_method == 'POST') {
      $scope = 'channels';
    }
    elseif (in_array($action, ['follow', 'unfollow', 'search', 'preview'])) {
      $scope = 'follow';
    }
    elseif ($action == 'channels' || $action == 'timeline') {
      $scope = 'read';
    }

    if (!$this->indieAuth->isValidToken($auth_header, $scope)) {
      return new JsonResponse('', 403);
    }

    // ---------------------------------------------------------
    // GET actions.
    // ---------------------------------------------------------

    if ($request_method == 'GET') {

      switch ($action) {

        case 'channels':
          $response = $this->getChannelList();
          break;

        case 'timeline':
          $response = $this->getTimeline();
          break;

        case 'follow':
          $response = $this->getSources();
          break;

        case 'search':
          $response = $this->search();
          break;

        case 'preview':
          $response = $this->previewUrl();
          break;

      }
    }

    // ---------------------------------------------------------
    // POST actions.
    // ---------------------------------------------------------

    if ($request_method == 'POST') {
      switch ($action) {

        // ---------------------------------------------------------
        // Channels
        // ---------------------------------------------------------

        case 'channels':
          $method = $request->get('method');
          if (!$method) {
            $method = 'create';
            if ($id = $request->get('channel')) {
              $method = 'update';
            }
          }

          if ($method == 'create') {
            $response = $this->createChannel();
          }

          if ($method == 'update') {
            $response = $this->updateChannel();
          }

          if ($method == 'order') {
            $response = $this->orderChannels();
          }

          if ($method == 'delete') {
            $response = $this->deleteChannel();
          }
          break;

        // ---------------------------------------------------------
        // Timeline
        // ---------------------------------------------------------

        case 'timeline':
          $method = $request->get('method');
          if (in_array($method, ['mark_read', 'mark_unread'])) {
            $status = $method == 'mark_read' ? 1 : 0;
            $response = $this->timelineChangeReadStatus($status);
          }

          if ($method == 'remove') {
            $response = $this->removeItem();
          }

          if ($method == 'move') {
            $response = $this->moveItem();
          }

          break;

        // ---------------------------------------------------------
        // Follow, Unfollow, Search and Preview
        // ---------------------------------------------------------

        case 'follow':
          $response = $this->followSource();
          break;

        case 'unfollow':
          $response = $this->deleteSource();
          break;

        case 'search':
          $response = $this->search();
          break;

        case 'preview':
          $response = $this->previewUrl();
          break;

      }
    }

    $response_message = isset($response['response']) ? $response['response'] : [];
    $response_code = isset($response['code']) ? $response['code'] : 200;

    return new JsonResponse($response_message, $response_code);
  }

  /**
   * Handle channels request.
   *
   * @return array $response
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getChannelList() {
    $channels = [];

    $ids = $this->entityTypeManager()
      ->getStorage('indieweb_microsub_channel')
      ->getQuery()
      ->condition('status', 1)
      ->sort('weight', 'ASC')
      ->execute();

    $channels_list = $this->entityTypeManager()->getStorage('indieweb_microsub_channel')->loadMultiple($ids);

    // Notifications channel.
    $notifications = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_item')->getUnreadCountByChannel(0);
    $channels[] = (object) [
      'uid' => 'notifications',
      'name' => 'Notifications',
      'unread' => (int) $notifications,
    ];

    /** @var \Drupal\indieweb_microsub\Entity\MicrosubChannelInterface $channel */
    foreach ($channels_list as $channel) {
      $unread = [];

      // Unread can either an int, boolean or omitted.
      if ($indicator = $channel->getReadIndicator()) {
        if ($indicator == MicrosubChannelInterface::readIndicatorCount) {
          $unread['unread'] = (int) $channel->getUnreadCount();
        }
        elseif ($indicator == MicrosubChannelInterface::readIndicatorNew) {
          $unread['unread'] = (bool) $channel->getUnreadCount();
        }
      }

      $channels[] = (object) ([
        'uid' => $channel->id(),
        'name' => $channel->label(),
      ] + $unread);

    }

    return ['response' => ['channels' => $channels]];
  }

  /**
   * Handle timeline request.
   *
   * @param $search
   *   Searches in posts.
   *
   * @return array $response
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getTimeline($search = NULL) {
    $response = ['items' => []];

    $items = [];
    $paging = [];

    /** @var \Drupal\indieweb_microsub\Entity\MicrosubItemInterface[] $microsub_items */
    $microsub_items = [];

    // Set pager.
    $page = $this->request->get('after', 0);
    if ($page > 0) {
      \Drupal::request()->query->set('page', $page);
    }

    // Is read.
    $is_read = $this->request->get('is_read');

    // Get source and channel variables.
    $source = $this->request->get('source');
    $channel = $this->request->get('channel');

    // ---------------------------------------------------------
    // Get items from a channel.
    // ---------------------------------------------------------

    // Notifications is stored as channel 0.
    if ($channel == 'notifications') {
      $channel = 0;
    }

    if (($channel || $channel === 0) && empty($search) && empty($source)) {
      $microsub_items = $this->entityTypeManager()
        ->getStorage('indieweb_microsub_item')
        ->loadByChannel($channel, $is_read);
    }

    // ---------------------------------------------------------
    // Search in a channel.
    // ---------------------------------------------------------

    if (!empty($search)) {
      $filter_by_channel = ($channel || $channel === 0) ? $channel : NULL;
      $microsub_items = $this->entityTypeManager()
        ->getStorage('indieweb_microsub_item')
        ->searchItems($search, $filter_by_channel, $is_read);
    }

    // ---------------------------------------------------------
    // Get items from a source.
    // ---------------------------------------------------------

    if ($source) {
      $microsub_items = $this->entityTypeManager()
        ->getStorage('indieweb_microsub_item')
        ->loadBySource($source, $is_read);
    }

    // If microsub items found, go get them.
    if (!empty($microsub_items)) {
      $author_name = '';
      foreach ($microsub_items as $item) {

        $data = $item->getData();
        // See https://github.com/swentel/indieweb/issues/325
        $fields_to_fix = ['in-reply-to', 'like-of', 'repost-of'];
        foreach ($fields_to_fix as $field) {
          if (isset($data->{$field})) {
            $flat = [];
            foreach ($data->{$field} as $field_value) {
              $flat[] = $field_value;
            }
            $data->{$field} = $flat;
          }
        }

        // Check author name.
        if ($source && !empty($data->author->name)) {
          $author_name = $data->author->name;
        }

        // Apply media cache.
        if ($channel > 0 && !$item->getSource()->disableImageCache()) {
          $this->applyCache($data);
        }

        $entry = $data;
        $entry->_id = $item->id();
        $entry->_is_read = $item->isRead();
        $entry->_source = $item->getSourceId();

        // Channel information.
        $channel_id = $item->getChannelId();
        if ($channel_id > 0) {
          $channel = $item->getSource()->getChannel()->label();
        }
        else {
          $channel = 'Notifications';
        }
        $entry->_channel = ['name' => $channel, 'id' => $channel_id];

        // Get context.
        if (!isset($entry->refs) && ($context = $item->getContext())) {
          // TODO fix when https://github.com/indieweb/jf2/issues/41 lands.
          $entry->refs = $context;
        }

        $items[] = $entry;
      }

      // Calculate pager and after.
      global $pager_total;
      $page++;
      if (isset($pager_total[0]) && $pager_total[0] > $page) {
        $paging = ['after' => $page];
      }

      $response = ['paging' => (object) $paging, 'items' => $items];

      if ($source) {
        $microsub_source = $this->entityTypeManager()->getStorage('indieweb_microsub_source')->load($source);
        if ($microsub_source) {
          $source_name = $microsub_source->label();
          if (strpos($source_name, 'granary') !== FALSE) {
            $source_name = 'Granary';
          }
          elseif (!empty($author_name)) {
            $source_name = $author_name;
          }
          $response['source'] = (object) ['name' => $source_name];
        }
      }
    }

    return ['response' => $response, 'code' => 200];
  }

  /**
   * Apply cache settings.
   *
   * @param $data
   */
  protected function applyCache($data) {

    // Author images.
    if (isset($data->author->photo)) {
      $data->author->photo = \Drupal::service('indieweb.media_cache.client')->applyImageCache($data->author->photo);
    }

    // Photos.
    if (isset($data->photo) && !empty($data->photo) && is_array($data->photo)) {
      foreach ($data->photo as $i => $p) {
        $data->photo[$i] = \Drupal::service('indieweb.media_cache.client')->applyImageCache($p, 'photo');
      }
    }

    // Images in html content.
    if (!empty($data->content->html)) {
      $data->content->html = \Drupal::service('indieweb.media_cache.client')->replaceImagesInString($data->content->html, 'photo');
    }
  }

  /**
   * Create a channel.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createChannel() {
    $return = ['response' => [], 'code' => 400];

    $name = $this->request->get('name');
    if (!empty($name)) {
      $uid = 1;
      if ($token_uid = $this->indieAuth->checkAuthor()) {
        $uid = $token_uid;
      }
      $values = ['title' => $name, 'uid' => $uid];
      $channel = $this->entityTypeManager()->getStorage('indieweb_microsub_channel')->create($values);
      $channel->save();
      if ($channel->label()) {
        $return = ['response' => ['uid' => $channel->id(), 'name' => $channel->label()], 'code' => 200];
      }
    }

    return $return;
  }

  /**
   * Updates a channel.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function updateChannel() {
    $return = ['response' => [], 'code' => 400];

    $id = $this->request->get('channel');
    $name = $this->request->get('name');
    if (!empty($name) && !empty($id)) {
      /** @var \Drupal\indieweb_microsub\Entity\MicrosubChannelInterface $channel */
      $channel = $this->entityTypeManager()->getStorage('indieweb_microsub_channel')->load($id);
      if ($channel) {
        $channel->set('title', $name)->save();
        $return = ['response' => ['uid' => $channel->id(), 'name' => $channel->label()], 'code' => 200];
      }
    }

    return $return;
  }

  /**
   * Deletes a channel.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function deleteChannel() {
    $return = ['response' => [], 'code' => 400];

    $id = $this->request->get('channel');
    if (!empty($id)) {
      $channel = $this->entityTypeManager()->getStorage('indieweb_microsub_channel')->load($id);
      if ($channel) {
        $channel->delete();
        $return = ['response' => [], 'code' => 200];
      }
    }

    return $return;
  }

  /**
   * Orders channels.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function orderChannels() {
    $return = ['response' => [], 'code' => 400];

    $ids = $this->request->get('channels');
    if (!empty($ids)) {
      $weight = -20;
      ksort($ids);
      foreach ($ids as $id) {
        /** @var \Drupal\indieweb_microsub\Entity\MicrosubChannelInterface $channel */
        $channel = $this->entityTypeManager()->getStorage('indieweb_microsub_channel')->load($id);
        if ($channel) {
          $channel->set('weight', $weight);
          $channel->save();
          $weight++;
        }
      }
      $return = ['response' => [], 'code' => 200];
    }

    return $return;
  }

  /**
   * Mark items (un)read for a channel.
   *
   * @param int $status
   *   The status.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function timelineChangeReadStatus($status) {

    $channel_id = $this->request->get('channel');

    // Notifications is stored as channel 0.
    if ($channel_id == 'notifications') {
      $channel_id = 0;
    }

    // Check entry or last_read_entry. If last_read_entry is passed in, we
    // completely ignore entries, this usually just means 'Mark all as read'.
    $entries = $this->request->get('entry');
    if ($channel_id != 'global') {
      $last_read_entry = $this->request->get('last_read_entry');
      if (!empty($last_read_entry)) {
        $entries = NULL;
      }
    }

    if ($channel_id || $channel_id === 0) {
      $this->entityTypeManager()->getStorage('indieweb_microsub_item')->changeReadStatus($channel_id, $status, $entries);
    }

    return ['response' => [], 'code' => 200];
  }

  /**
   * Follow or update a source.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function followSource() {
    $return = ['response' => [], 'code' => 400];

    $url = $this->request->get('url');
    $channel_id = $this->request->get('channel');
    $method = $this->request->get('method');

    if (!empty($channel_id) && !empty($url)) {
      $channel = $this->entityTypeManager()->getStorage('indieweb_microsub_channel')->load($channel_id);
      if ($channel) {

        $uid = 1;
        if ($token_uid = $this->indieAuth->checkAuthor()) {
          $uid = $token_uid;
        }

        if ($method == 'update') {
          /** @var \Drupal\indieweb_microsub\Entity\MicrosubSourceInterface $source */
          $sources = $this->entityTypeManager()->getStorage('indieweb_microsub_source')->loadByProperties(['url' => $url]);
          if (!empty($sources) && count($sources) == 1) {
            $source = array_shift($sources);
            $source->set('channel_id', $channel_id);
            $source->save();
          }
        }
        else {
          $values = [
            'uid' => $uid,
            'url' => $url,
            'channel_id' => $channel_id,
            'fetch_interval' => 86400,
          ];
          $source = $this->entityTypeManager()->getStorage('indieweb_microsub_source')->create($values);
          $source->save();
        }
        $return = ['response' => ['type' => 'feed', 'url' => $url], 'code' => 200];
      }
    }

    return $return;
  }

  /**
   * Delete a source.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function deleteSource() {
    $return = ['response' => [], 'code' => 400];

    $url = $this->request->get('url');
    $channel_id = $this->request->get('channel');
    if (!empty($channel_id) && !empty($url)) {
      $sources = $this->entityTypeManager()->getStorage('indieweb_microsub_source')->loadByProperties(['url' => $url, 'channel_id' => $channel_id]);
      if (count($sources) == 1) {
        $source = array_shift($sources);
        $source->delete();
        $return = ['response' => [], 'code' => 200];
      }
    }

    return $return;
  }

  /**
   * Get sources.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getSources() {
    $return = ['response' => [], 'code' => 400];

    $channel_id = $this->request->get('channel');
    if (!empty($channel_id)) {
      $sources = $this->entityTypeManager()->getStorage('indieweb_microsub_source')->loadByProperties(['channel_id' => $channel_id]);
      if (!empty($sources)) {
        $source_list = [];
        foreach ($sources as $source) {
          $source_list[] = (object) [
            'type' => 'feed',
            'url' => $source->label(),
          ];
        }
        $return = ['response' => (object) ['items' => $source_list], 'code' => 200];
      }
    }

    return $return;
  }

  /**
   * Search.
   *
   * This either searches for posts, or for new feeds to subscribe to.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function search() {
    $return = ['response' => [], 'code' => 400];

    $channel = NULL;
    $query = $this->request->get('query');

    // Search for posts, but only in a POST request.
    if ($this->request->getMethod() == 'POST') {
      $channel = $this->request->get('channel');

      // Notifications is stored as channel 0.
      if ($channel == 'notifications') {
        $channel = 0;
      }
    }

    if (!empty($query)) {

      // Search for posts.
      if ($channel || $channel === 0) {
        $return = $this->getTimeline($query);
      }

      // Search for feeds.
      else {
        /** @var \Drupal\indieweb_microsub\MicrosubClient\MicrosubClientInterface $microsubClient */
        $microsubClient = \Drupal::service('indieweb.microsub.client');
        $feeds = $microsubClient->searchFeeds($query);
        if (!empty($feeds['feeds'])) {
          $result_list = [];
          foreach ($feeds['feeds'] as $feed) {
            $result_list[] = (object) [
              'type' => $feed['type'],
              'url' => $feed['url'],
            ];
          }
          $return = ['response' => (object) ['results' => $result_list], 'code' => 200];
        }
      }

    }

    return $return;
  }

  /**
   * Preview url.
   *
   * @return array
   */
  protected function previewUrl() {
    $return = ['response' => [], 'code' => 400];

    $url = $this->request->get('url');
    if (!empty($url)) {
      try {
        $xray = new XRay();
        $response = \Drupal::httpClient()->get($url);
        $body = $response->getBody()->getContents();
        $parsed = $xray->parse($url, $body, ['expect' => 'feed']);
        if ($parsed && isset($parsed['data']['type']) && $parsed['data']['type'] == 'feed') {
          $return = ['response' => (object) ['items' => $parsed['data']['items']], 'code' => 200];
        }
      }
      catch (\Exception $e) {
        \Drupal::logger('indieweb_microsub')->notice('Error fetching preview for @url : @message', ['@url' => $url, '@message' => $e->getMessage()]);
      }
    }

    return $return;
  }

  /**
   * Removes a microsub item
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function removeItem() {

    $entry_id = $this->request->get('entry');
    if ($entry_id) {
     $this->entityTypeManager()->getStorage('indieweb_microsub_item')->removeItem($entry_id);
    }

    return ['response' => [], 'code' => 200];
  }

  /**
   * Moves a microsub item.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function moveItem() {

    $channel_id = $this->request->get('channel');

    // Notifications is stored as channel 0.
    if ($channel_id == 'notifications') {
      $channel_id = 0;
    }

    $entries = $this->request->get('entry');
    if ($entries && ($channel_id || $channel_id === 0)) {
      $this->entityTypeManager()->getStorage('indieweb_microsub_item')->moveItem($entries, $channel_id);
    }

    return ['response' => [], 'code' => 200];
  }

  /**
   * Microsub channel overview.
   *
   * @return array
   */
  public function channelOverview() {
    return $this->entityTypeManager()->getListBuilder('indieweb_microsub_channel')->render();
  }

  /**
   * Microsub sources overview.
   *
   * @param \Drupal\indieweb_microsub\Entity\MicrosubChannelInterface $indieweb_microsub_channel
   *
   * @return array
   */
  public function sourcesOverview(MicrosubChannelInterface $indieweb_microsub_channel) {
    return $this->entityTypeManager()->getListBuilder('indieweb_microsub_source')->render($indieweb_microsub_channel);
  }

  /**
   * Reset fetch next time for a source.
   *
   * @param \Drupal\indieweb_microsub\Entity\MicrosubSourceInterface $indieweb_microsub_source
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function resetNextFetch(MicrosubSourceInterface $indieweb_microsub_source) {
    $indieweb_microsub_source->setNextFetch(0)->save();
    $this->messenger()->addMessage($this->t('Next update reset for %source', ['%source' => $indieweb_microsub_source->label()]));
    return new RedirectResponse(Url::fromRoute('indieweb.admin.microsub_sources', ['indieweb_microsub_channel' => $indieweb_microsub_source->getChannelId()])->toString());
  }

}
