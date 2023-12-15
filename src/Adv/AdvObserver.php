<?php namespace Visiosoft\AdvsModule\Adv;

use Visiosoft\AdvsModule\Adv\Command\AddSlug;
use Visiosoft\AdvsModule\Adv\Command\DeleteOptionConfiguration;
use Anomaly\Streams\Platform\Entry\Contract\EntryInterface;
use Anomaly\Streams\Platform\Entry\EntryObserver;
use Visiosoft\AdvsModule\Adv\Event\DeletedAd;
use Visiosoft\AdvsModule\Adv\Event\DeletingAd;

class AdvObserver extends EntryObserver
{
    private function translateFixer($entryId)
    {
        $defaultLocale = setting_value('streams::default_locale');
        $translations = DB::table('advs_advs_translations')
            ->select(['locale', 'name'])
            ->where('entry_id', $entryId)
            ->whereNotNull('locale')
            ->get();

        if ($translations) {
            $name = "";
            foreach ($translations as $translation) {
                if ($translation->locale == $defaultLocale && !empty($translation->name)) {
                    $name = $translation->name;
                    break;
                } else if (!empty($translation->name)) {
                    $name = $translation->name;
                }
            }

            DB::table('advs_advs_translations')
                ->whereNull('name')
                ->where('entry_id', $entryId)
                ->update(['name' => $name]);
        }

    }

    public function created(EntryInterface $entry)
    {
        $this->translateFixer($entry->getId());
    }

    public function updating(EntryInterface $entry)
    {
        $this->dispatchSync(new AddSlug($entry));
        $this->translateFixer($entry->getId());

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
