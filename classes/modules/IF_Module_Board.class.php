<?php
/**
 * IF_Module_Board.class.php
 * Defines the Board module class
 *   
 * @author Damien Walsh <walshd0@cs.man.ac.uk>
 */
/**
 * The Board module class.
 *
 * The Board module provides access to the main board parameters and
 * operations.
 *
 * @package IF
 */
class IF_Module_Board extends IF_Module
{
  /**
   * Get the title of the board.
   * 
   * @return array
   */
  public function getTitle()
  {
    // Retrieve the title
    return array(
      'attribute' => 'title', 
      'value' => $this->parent->modules['Config']->get('board_title')
      );
  }

  /**
   * Get the forums that are available on this board.
   * 
   * @return array
   */
  public function getForums()
  {
    $result = $this->parent->DB->select('if_forums');
    $rows = array();

    // Count topics and posts
    foreach($result->rows as $row)
    {
      // Count topics
      $topics = $this->parent->DB->select('if_topics',
        Predicate::_equal(new Value('topic_forum_id'), $row['forum_id']));

      // Count posts
      $posts = $this->parent->DB->select('if_posts',
        Predicate::_equal(new Value('post_forum_id'), $row['forum_id']));

      // Check the permissions allow the current user to see this forum
      if(!$this->parent->modules['User']->can('read', $row['forum_id']))
      {
        // No permission!
        continue;
      }

      $rows[] = array(
        'forum_id' => $row['forum_id'],
        'forum_title' => $row['forum_title'],
        'forum_topics' => $topics->count,
        'forum_posts' => $posts->count
      );
    }
    return $rows;
  }

  /** 
   * Get the topics for a speciifed forum.
   *
   * @param integer $ID The ID of the forum to get the topics for.
   * @return array
   */
  public function getTopics($ID)
  {
    $topics = $this->parent->DB->select('if_topics',
      Predicate::_equal(new Value('topic_forum_id'), $ID));
    $rows = array();

    // Can I see this forum?
    if(!$this->parent->modules['User']->can('read', $ID))
    {
      return array(
        'topics' => array(),
        'forum_id' => $ID,
        'can_create_new' => false
      );
    }

    foreach($topics->rows as $row)
    {
      // Count posts
      $posts = $this->parent->DB->select('if_posts',
        Predicate::_equal(new Value('post_topic_id'), $row['topic_id']));

      // Get the owner of the topic
      $owner = $this->parent->DB->select('if_users',
        Predicate::_equal(new Value('user_id'), $row['topic_owner_id']));

      // Still exists? Reassign to the name
      if($owner->count == 1)
      {
        $ownerUser = $owner->next();
        $owner = $ownerUser->user_full_name;
      }
      else
      {
        $owner = 'Guest User';
      }

      $rows[] = array(
        'topic_id' => $row['topic_id'],
        'topic_title' => $row['topic_name'],
        'topic_posts' => $posts->count,
        'topic_owner' => $owner,
        'forum_id' => $ID
      );
    }

    return array(
      'topics' => $rows,
      'forum_id' => $ID,
      'can_create_new' => $this->parent->modules['User']->can('new_topic', $ID)
    );
  }

  /** 
   * Get the posts for a specified topic.
   *
   * @param integer $ID The ID of the topic to get the posts for.
   * @return array
   */
  public function getPosts($ID)
  {
    $posts = $this->parent->DB->select('if_posts',
      Predicate::_equal(new Value('post_topic_id'), $ID));

    $rows = array();

    foreach($posts->rows as $row)
    {
      // Get the owner of the post
      $owner = $this->parent->DB->select('if_users',
        Predicate::_equal(new Value('user_id'), $row['post_owner_id']));

      // Still exists? Reassign to the name
      if($owner->count == 1)
      {
        $ownerUser = $owner->next();
        $owner = $ownerUser->user_full_name;
      }
      else
      {
        $owner = 'Guest User';
      }

      // Count topics and posts
      $rows[] = array(
        'post_id' => $row['post_id'],
        'post_text' => $row['post_text'],
        'post_owner' => $owner
      );
    }

    // Get the topic information too
    $topic = $this->parent->DB->select('if_topics',
      Predicate::_equal(new Value('topic_id'), $ID));
    
    return array(
      'posts' => $rows,
      'topic_id' => $ID,
      'topic_name' => $topic->rows[0]['topic_name'],
      'forum_id' => $topic->rows[0]['topic_forum_id'],
      'can_post' => 
        $this->parent->modules['User']->can('post', $topic->rows[0]['topic_forum_id'])
    );
  }

  /** 
   * Add a post to the specified topic
   *
   * @param integer $ID The ID of the topic to add a post to.
   * @param string $text The text to add.
   * @return array
   */
  public function addPost($ID, $text)
  {
    // Get the topic
    $topic = $this->parent->DB->select('if_topics',
      Predicate::_equal(new Value('topic_id'), $ID));

    // Exists?
    if($topic->count == 0)
    {
      return false;
    }

    $topic = $topic->next();

    if(!$this->parent->modules['User']->can('post', $topic->topic_forum_id))
    {
      // No permission!
      return false;
    }

    // Insert a post
    $this->parent->DB->insert('if_posts', array(
      'post_topic_id' => $ID,
      'post_text' => strip_tags($text)
    ));

    return array($ID);
  }

  /** 
   * Add a topic to the specified forum
   *
   * @param integer $ID The ID of the forum to add a topic to.
   * @param string $name The name of the topic.
   * @param string $text The text to add.
   * @return array
   */
  public function addTopic($ID, $name, $text)
  {
    if(!$this->parent->modules['User']->can('new_topic', $ID))
    {
      // No permission!
      return false;
    }

    // Insert a topic
    $newTopic = $this->parent->DB->insert('if_topics', array(
      'topic_id' => NULL,
      'topic_name' => $name,
      'topic_forum_id' => $ID,
      'topic_owner_id' => $_SESSION['user_id']
    ));

    // Insert the post into the new topic
    if(!$newTopic->autos['topic_id'])
    {
      return false;
    }

    // Add post
    $newPost = $this->parent->DB->insert('if_posts', array(
      'post_id' => NULL,
      'post_text' => $text,
      'post_forum_id' => $ID,
      'post_topic_id' => $newTopic->autos['topic_id'],
      'post_owner_id' => $_SESSION['user_id']
    ));

    return $newTopic->autos['topic_id'];
  }
}