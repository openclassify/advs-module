<?php namespace Visiosoft\AdvsModule\Http\Controller;

use Anomaly\SettingsModule\Setting\Contract\SettingRepositoryInterface;
use Anomaly\Streams\Platform\Http\Controller\PublicController;
use Anomaly\Streams\Platform\Model\Complaints\ComplaintsComplainTypesEntryModel;
use Anomaly\Streams\Platform\Support\Currency;
use Anomaly\UsersModule\User\Contract\UserRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use \Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Visiosoft\AdvsModule\Adv\AdvModel;
use Visiosoft\AdvsModule\Adv\Command\IsOptionsByCategory;
use Visiosoft\AdvsModule\Adv\Command\UpdateClassifiedStatus;
use Visiosoft\AdvsModule\Adv\Contract\AdvRepositoryInterface;
use Visiosoft\AdvsModule\Adv\Event\CreatedAd;
use Visiosoft\AdvsModule\Adv\Event\EditCoorAd;
use Visiosoft\AdvsModule\Adv\Event\EditedAd;
use Visiosoft\AdvsModule\Adv\Event\EditedAdCategory;
use Visiosoft\AdvsModule\Adv\Event\PriceChange;
use Visiosoft\AdvsModule\Adv\Event\ViewAd;
use Visiosoft\AdvsModule\Adv\Form\AdvFormBuilder;
use Visiosoft\AdvsModule\EidsService;
use Visiosoft\AdvsModule\Option\Contract\OptionRepositoryInterface;
use Visiosoft\AdvsModule\OptionConfiguration\Contract\OptionConfigurationRepositoryInterface;
use Visiosoft\AdvsModule\OptionConfiguration\OptionConfigurationModel;
use Visiosoft\CatsModule\Category\Contract\CategoryRepositoryInterface;
use Visiosoft\LocationModule\City\CityModel;
use Visiosoft\LocationModule\City\CityRepository;
use Visiosoft\LocationModule\Country\Contract\CountryRepositoryInterface;
use Visiosoft\LocationModule\District\DistrictModel;
use Visiosoft\LocationModule\District\DistrictRepository;
use Visiosoft\LocationModule\Neighborhood\NeighborhoodModel;
use Visiosoft\LocationModule\Neighborhood\NeighborhoodRepository;
use Visiosoft\LocationModule\Village\VillageModel;
use Visiosoft\LocationModule\Village\VillageRepository;
use Visiosoft\PackagesModule\AdvsLog\Contract\AdvsLogRepositoryInterface;
use Visiosoft\PackagesModule\Package\Contract\PackageRepositoryInterface;
use Visiosoft\PackagesModule\Userentry\Contract\UserentryRepositoryInterface;
use Visiosoft\ProfileModule\Adress\Contract\AdressRepositoryInterface;
use Visiosoft\SeoModule\Legend\Command\AddMetaData;
use Visiosoft\StoreModule\Store\Contract\StoreRepositoryInterface;
use Illuminate\Support\Str;

class EidsController extends PublicController
{
    private $userRepository;

    public function __construct(
        UserRepositoryInterface $userRepository
    )
    {
        $this->userRepository = $userRepository;
        parent::__construct();
    }

    public function eidsReturn(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();
        if ($request->durum === "Basarili") {
            $user->is_eids_verified = true;
            $user->eids_auth_code = $request->yetkiKodu;
            $user->save();
        }

        return $this->redirect->to(route('advs::create_adv'));
    }

}
