<?php namespace Visiosoft\AdvsModule\Adv;

use Visiosoft\AdvsModule\Adv\Command\AddSlug;
use Visiosoft\AdvsModule\Adv\Command\DeleteOptionConfiguration;
use Anomaly\Streams\Platform\Entry\Contract\EntryInterface;
use Anomaly\Streams\Platform\Entry\EntryObserver;
use Visiosoft\AdvsModule\Adv\Event\DeletedAd;
use Visiosoft\AdvsModule\Adv\Event\DeletingAd;

class AdvObserver extends EntryObserver
{
    public function updating(EntryInterface $entry)
    {
        $this->dispatchSync(new AddSlug($entry));

        parent::updating($entry);
    }

    public function deleting(EntryInterface $entry)
    {
        $this->dispatchSync(new DeleteOptionConfiguration($entry));

        event(new DeletingAd($entry));

        parent::deleting($entry);
    }

    public function deleted(EntryInterface $entry)
    {
        event(new DeletedAd($entry));
        parent::deleted($entry);
    }
}
