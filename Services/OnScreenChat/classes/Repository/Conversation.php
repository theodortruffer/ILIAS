<?php declare(strict_types=1);
/* Copyright (c) 1998-2019 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\OnScreenChat\Repository;

use ILIAS\OnScreenChat\DTO\ConversationDto;
use ILIAS\OnScreenChat\DTO\MessageDto;

/**
 * Class Conversation
 * @package ILIAS\OnScreenChat\DTO
 */
class Conversation
{
    /** @var \ilDBInterface */
    private $db;

    /**
     * Conversation constructor.
     */
    public function __construct(\ilDBInterface $db)
    {
        $this->db = $db;
    }

    /**
     * @param string[] $conversationIds
     * @param \ilObjUser $user
     * @return ConversationDto[]
     */
    public function findByIdsAndUser(array $conversationIds, \ilObjUser $user) : array 
    {
        $conversations = [];

        $res = $this->db->query(
            'SELECT * FROM osc_conversation WHERE ' . $this->db->in(
                'id', $conversationIds, false, 'text'
            )
        );

        while ($row = $this->db->fetchAssoc($res)) {
            $participants = json_decode($row['participants'], true);
            $participantIds = array_filter(array_map(function ($value) {
                if (is_array($value) && isset($value['id'])) {
                    return (int) $value['id'];
                }

                return 0;
            }, $participants));

            if (!in_array((int) $user->getId(), $participantIds)) {
                continue;
            }
            
            $conversation = new ConversationDto($row['id']);
            $conversation->setIsGroup((bool) $row['osc_']);
            $conversation->setSubscriberUsrIds($participantIds);

            $this->db->setLimit(1, 0);
            $query = "
                SELECT osc_messages.*
                FROM osc_messages
                WHERE osc_messages.conversation_id = %s
                AND {$this->db->in(
                    'osc_messages.user_id', $participantIds, false, 'text'
                )}
                ORDER BY osc_messages.timestamp DESC
            ";
            $msgRes = $this->db->queryF($query, ['text'], [$conversation->getId()]);

            while ($msgRow = $this->db->fetchAssoc($msgRes)) {
                $message = new MessageDto($msgRow['id'], $conversation);
                $message->setMessage($msgRow['message']);
                $message->setAuthorUsrId((int) $msgRow['user_id']);
                $message->setCreatedTimestamp((int) $msgRow['timestamp']);
                $conversation->setLastMessage($message);
                break;
            }

            $conversations[] = $conversation;
        }

        return $conversations;
    }
}