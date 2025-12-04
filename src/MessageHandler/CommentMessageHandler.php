<?php

namespace App\MessageHandler;

use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\SpamChecker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class CommentMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SpamChecker $spamChecker,
        private CommentRepository $commentRepository,
        private MessageBusInterface $bus,
        private WorkflowInterface $commentStateMachine,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(CommentMessage $message)
    {
        $comment = $this->commentRepository->find($message->getId());
        if (!$comment) {
            return;
        }

        if ($this->commentStateMachine->can($comment, 'accept')) {
            $score = $this->spamChecker->getSpamScore($comment, $message->getContext());
            $transition = match ($score) {
                2 => 'reject_spam',
                1 => 'might_be_spam',
                default => 'accept',
            };
            $this->commentStateMachine->apply($comment, $transition);
            $this->entityManager->flush();
            $this->bus->dispatch($message);
			return;
        }
		if ($this->commentStateMachine->can($comment, 'publish_ham')) {
            $this->commentStateMachine->apply($comment, 'publish_ham');
            $this->entityManager->flush();
			return;
        }
		if ($this->commentStateMachine->can($comment, 'publish')) {
            $this->commentStateMachine->apply($comment, 'publish');
            $this->entityManager->flush();
			return;
        }
		
		if ($this->logger) {
            $this->logger->debug('No processing was applied to comment message', ['comment' => $comment->getId(), 'state' => $comment->getState()]);
		}
    }
}