<?php

/**
 * @file
 * Contains \Drupal\activity_creator\ActivityAccessControlHandler.
 */

namespace Drupal\activity_creator;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Activity entity.
 *
 * @see \Drupal\activity_creator\Entity\Activity.
 */
class ActivityAccessControlHandler extends EntityAccessControlHandler {
  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\activity_creator\ActivityInterface $entity */
    switch ($operation) {
      case 'view':
        $recipient = $this->getRecipientFromActivity($entity);
        if ($recipient === NULL) {
          // This is simple, the message is not specific for group / user.
          // So we should check, does the user have permission to view entity?
          return $this->returnAccessRelatedObject($entity, $operation, $account);
        }
        else {
          $recipient_type = $recipient['0']['target_type'];
          if ($recipient_type === 'user') {
            $recipient_id = $recipient['0']['target_id'];
            // If it is personalised, lets check recipient id vs account id.
            if ($this->checkIfPersonalNotification($entity) === TRUE) {
              return AccessResult::allowedIf($account->id() === $recipient_id);
            }
            else {
              // Lets fallback to the related object access permission.
              return $this->returnAccessRelatedObject($entity, $operation, $account);
            }
          }
          if ($recipient_type === 'group') {

          }
        }
        return AccessResult::allowedIfHasPermission($account, 'view all published activity entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit activity entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete activity entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add activity entities');
  }

  /**
   * Get recipient.
   */
  protected function getRecipientFromActivity(EntityInterface $entity) {
    $value = NULL;
    $recipient_user = $entity->get('field_activity_recipient_user')->getValue();
    if (!empty($recipient_user)) {
      $recipient_user['0']['target_type'] = 'user';
      return $recipient_user;
    }
    $recipient_group = $entity->get('field_activity_recipient_group')->getValue();
    if (!empty($recipient_group)) {
      $recipient_group['0']['target_type'] = 'group';
      return $recipient_group;
    }
    return $value;
  }

  /**
   * Get destinations.
   */
  protected function getDestinationFromActivity(EntityInterface $entity) {
    $destinations = $entity->get('field_activity_destinations')->getValue();
    return $destinations;
  }

  /**
   * Return access control from the related entity.
   */
  protected function returnAccessRelatedObject(EntityInterface $entity, $operation, $account) {
    $related_object = $entity->get('field_activity_entity')->getValue();
    if (!empty($related_object)) {
      $ref_entity_type = $related_object['0']['target_type'];
      $ref_entity_id = $related_object['0']['target_id'];
      $ref_entity = entity_load($ref_entity_type, $ref_entity_id);

      return AccessResult::allowedIf($ref_entity->access($operation, $account));
    }
  }

  /**
   * Check if this is a personal notification.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *    Entity object.
   *
   * @return bool
   *    Returns TRUE if entity is personal notification, FALSE if it isn't.
   */
  protected function checkIfPersonalNotification(EntityInterface $entity) {
    $recipient = $this->getRecipientFromActivity($entity);
    $value = FALSE;
    if (!empty($recipient) && $recipient['0']['target_type'] === 'user') {
      // This could be personalised, but first lets check the destinations.
      $is_notification = FALSE;
      $destinations = $this->getDestinationFromActivity($entity);
      foreach ($destinations as $key => $destination) {
        if ($destination['value'] === 'notifications') {
          $is_notification = TRUE;
        }
      }
      // It is only personal if the only destination is notifications.
      if (count($destinations) <= 1 && $is_notification === TRUE) {
        $value = TRUE;
      }
    }
    return $value;
  }

}
