<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a webmention deletion confirmation form.
 *
 * @internal
 */
class WebmentionDeleteMultiple extends ConfirmFormBase {

  /**
   * The array of webmentions to delete.
   *
   * @var string[][]
   */
  protected $webmentionInfo = [];

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Constructs a DeleteMultiple form object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Entity\EntityManagerInterface $manager
   *   The entity manager.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, EntityManagerInterface $manager) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->storage = $manager->getStorage('webmention_entity');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webmention_multiple_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->formatPlural(count($this->webmentionInfo), 'Are you sure you want to delete this item?', 'Are you sure you want to delete these items?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.webmention_entity.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->webmentionInfo = $this->tempStoreFactory->get('webmention_multiple_delete_confirm')->get(\Drupal::currentUser()->id());
    if (empty($this->webmentionInfo)) {
      return new RedirectResponse($this->getCancelUrl()->setAbsolute()->toString());
    }
    /** @var \Drupal\indieweb\Entity\WebmentionInterface[] $webmentions */
    $webmentions = $this->storage->loadMultiple(array_keys($this->webmentionInfo));

    $items = [];
    foreach ($webmentions as $webmention) {
      $items[$webmention->id()] = $webmention->label();
    }

    $form['webmentions'] = [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('confirm') && !empty($this->webmentionInfo)) {

      /** @var \Drupal\indieweb\Entity\WebmentionInterface[] $webmentions */
      $webmentions = $this->storage->loadMultiple(array_keys($this->webmentionInfo));
      $total_count = count($webmentions);

      if ($webmentions) {
        $this->storage->delete($webmentions);
        $this->logger('content')->notice('Deleted @count posts.', ['@count' => $total_count]);
      }

      if ($total_count) {
        drupal_set_message($this->formatPlural($total_count, 'Deleted 1 webmention.', 'Deleted @count webmentions.'));
      }

      $this->tempStoreFactory->get('webmention_multiple_delete_confirm')->delete(\Drupal::currentUser()->id());
    }

    $form_state->setRedirect('entity.webmention_entity.collection');
  }

}
