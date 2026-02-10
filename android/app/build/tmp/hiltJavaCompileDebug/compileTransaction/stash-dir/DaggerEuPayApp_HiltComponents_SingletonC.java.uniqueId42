package nl.delaparra_services.apps.eupay;

import android.app.Activity;
import android.app.Service;
import android.content.SharedPreferences;
import android.view.View;
import androidx.credentials.CredentialManager;
import androidx.fragment.app.Fragment;
import androidx.lifecycle.SavedStateHandle;
import androidx.lifecycle.ViewModel;
import com.google.gson.Gson;
import dagger.hilt.android.ActivityRetainedLifecycle;
import dagger.hilt.android.ViewModelLifecycle;
import dagger.hilt.android.internal.builders.ActivityComponentBuilder;
import dagger.hilt.android.internal.builders.ActivityRetainedComponentBuilder;
import dagger.hilt.android.internal.builders.FragmentComponentBuilder;
import dagger.hilt.android.internal.builders.ServiceComponentBuilder;
import dagger.hilt.android.internal.builders.ViewComponentBuilder;
import dagger.hilt.android.internal.builders.ViewModelComponentBuilder;
import dagger.hilt.android.internal.builders.ViewWithFragmentComponentBuilder;
import dagger.hilt.android.internal.lifecycle.DefaultViewModelFactories;
import dagger.hilt.android.internal.lifecycle.DefaultViewModelFactories_InternalFactoryFactory_Factory;
import dagger.hilt.android.internal.managers.ActivityRetainedComponentManager_LifecycleModule_ProvideActivityRetainedLifecycleFactory;
import dagger.hilt.android.internal.managers.SavedStateHandleHolder;
import dagger.hilt.android.internal.modules.ApplicationContextModule;
import dagger.hilt.android.internal.modules.ApplicationContextModule_ProvideApplicationFactory;
import dagger.hilt.android.internal.modules.ApplicationContextModule_ProvideContextFactory;
import dagger.internal.DaggerGenerated;
import dagger.internal.DoubleCheck;
import dagger.internal.LazyClassKeyMap;
import dagger.internal.MapBuilder;
import dagger.internal.Preconditions;
import dagger.internal.Provider;
import java.util.Collections;
import java.util.Map;
import java.util.Set;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.api.EuPayApi;
import nl.delaparra_services.apps.eupay.crypto.ClientKeyManager;
import nl.delaparra_services.apps.eupay.di.AppModule_ProvideApiFactory;
import nl.delaparra_services.apps.eupay.di.AppModule_ProvideAuthServiceFactory;
import nl.delaparra_services.apps.eupay.di.AppModule_ProvideCardServiceFactory;
import nl.delaparra_services.apps.eupay.di.AppModule_ProvideClientKeyManagerFactory;
import nl.delaparra_services.apps.eupay.di.AppModule_ProvideCredentialManagerFactory;
import nl.delaparra_services.apps.eupay.di.AppModule_ProvideEncryptedPrefsFactory;
import nl.delaparra_services.apps.eupay.di.AppModule_ProvideGsonFactory;
import nl.delaparra_services.apps.eupay.di.AppModule_ProvideOkHttpFactory;
import nl.delaparra_services.apps.eupay.di.AppModule_ProvidePasskeyServiceFactory;
import nl.delaparra_services.apps.eupay.di.AppModule_ProvidePaymentServiceFactory;
import nl.delaparra_services.apps.eupay.di.AppModule_ProvideRetrofitFactory;
import nl.delaparra_services.apps.eupay.di.AppModule_ProvideTokenRepositoryFactory;
import nl.delaparra_services.apps.eupay.repository.TokenRepository;
import nl.delaparra_services.apps.eupay.service.AuthService;
import nl.delaparra_services.apps.eupay.service.CardService;
import nl.delaparra_services.apps.eupay.service.P2PService;
import nl.delaparra_services.apps.eupay.service.PasskeyService;
import nl.delaparra_services.apps.eupay.service.PaymentService;
import nl.delaparra_services.apps.eupay.service.TopUpService;
import nl.delaparra_services.apps.eupay.ui.MainActivity;
import nl.delaparra_services.apps.eupay.ui.MainActivity_MembersInjector;
import nl.delaparra_services.apps.eupay.ui.viewmodel.AuthViewModel;
import nl.delaparra_services.apps.eupay.ui.viewmodel.AuthViewModel_HiltModules;
import nl.delaparra_services.apps.eupay.ui.viewmodel.AuthViewModel_HiltModules_BindsModule_Binds_LazyMapKey;
import nl.delaparra_services.apps.eupay.ui.viewmodel.AuthViewModel_HiltModules_KeyModule_Provide_LazyMapKey;
import nl.delaparra_services.apps.eupay.ui.viewmodel.CardsViewModel;
import nl.delaparra_services.apps.eupay.ui.viewmodel.CardsViewModel_HiltModules;
import nl.delaparra_services.apps.eupay.ui.viewmodel.CardsViewModel_HiltModules_BindsModule_Binds_LazyMapKey;
import nl.delaparra_services.apps.eupay.ui.viewmodel.CardsViewModel_HiltModules_KeyModule_Provide_LazyMapKey;
import nl.delaparra_services.apps.eupay.ui.viewmodel.HomeViewModel;
import nl.delaparra_services.apps.eupay.ui.viewmodel.HomeViewModel_HiltModules;
import nl.delaparra_services.apps.eupay.ui.viewmodel.HomeViewModel_HiltModules_BindsModule_Binds_LazyMapKey;
import nl.delaparra_services.apps.eupay.ui.viewmodel.HomeViewModel_HiltModules_KeyModule_Provide_LazyMapKey;
import nl.delaparra_services.apps.eupay.ui.viewmodel.PayViewModel;
import nl.delaparra_services.apps.eupay.ui.viewmodel.PayViewModel_HiltModules;
import nl.delaparra_services.apps.eupay.ui.viewmodel.PayViewModel_HiltModules_BindsModule_Binds_LazyMapKey;
import nl.delaparra_services.apps.eupay.ui.viewmodel.PayViewModel_HiltModules_KeyModule_Provide_LazyMapKey;
import nl.delaparra_services.apps.eupay.ui.viewmodel.SendViewModel;
import nl.delaparra_services.apps.eupay.ui.viewmodel.SendViewModel_HiltModules;
import nl.delaparra_services.apps.eupay.ui.viewmodel.SendViewModel_HiltModules_BindsModule_Binds_LazyMapKey;
import nl.delaparra_services.apps.eupay.ui.viewmodel.SendViewModel_HiltModules_KeyModule_Provide_LazyMapKey;
import nl.delaparra_services.apps.eupay.ui.viewmodel.SettingsViewModel;
import nl.delaparra_services.apps.eupay.ui.viewmodel.SettingsViewModel_HiltModules;
import nl.delaparra_services.apps.eupay.ui.viewmodel.SettingsViewModel_HiltModules_BindsModule_Binds_LazyMapKey;
import nl.delaparra_services.apps.eupay.ui.viewmodel.SettingsViewModel_HiltModules_KeyModule_Provide_LazyMapKey;
import nl.delaparra_services.apps.eupay.ui.viewmodel.TopUpViewModel;
import nl.delaparra_services.apps.eupay.ui.viewmodel.TopUpViewModel_HiltModules;
import nl.delaparra_services.apps.eupay.ui.viewmodel.TopUpViewModel_HiltModules_BindsModule_Binds_LazyMapKey;
import nl.delaparra_services.apps.eupay.ui.viewmodel.TopUpViewModel_HiltModules_KeyModule_Provide_LazyMapKey;
import okhttp3.OkHttpClient;
import retrofit2.Retrofit;

@DaggerGenerated
@Generated(
    value = "dagger.internal.codegen.ComponentProcessor",
    comments = "https://dagger.dev"
)
@SuppressWarnings({
    "unchecked",
    "rawtypes",
    "KotlinInternal",
    "KotlinInternalInJava",
    "cast",
    "deprecation",
    "nullness:initialization.field.uninitialized"
})
public final class DaggerEuPayApp_HiltComponents_SingletonC {
  private DaggerEuPayApp_HiltComponents_SingletonC() {
  }

  public static Builder builder() {
    return new Builder();
  }

  public static final class Builder {
    private ApplicationContextModule applicationContextModule;

    private Builder() {
    }

    public Builder applicationContextModule(ApplicationContextModule applicationContextModule) {
      this.applicationContextModule = Preconditions.checkNotNull(applicationContextModule);
      return this;
    }

    public EuPayApp_HiltComponents.SingletonC build() {
      Preconditions.checkBuilderRequirement(applicationContextModule, ApplicationContextModule.class);
      return new SingletonCImpl(applicationContextModule);
    }
  }

  private static final class ActivityRetainedCBuilder implements EuPayApp_HiltComponents.ActivityRetainedC.Builder {
    private final SingletonCImpl singletonCImpl;

    private SavedStateHandleHolder savedStateHandleHolder;

    private ActivityRetainedCBuilder(SingletonCImpl singletonCImpl) {
      this.singletonCImpl = singletonCImpl;
    }

    @Override
    public ActivityRetainedCBuilder savedStateHandleHolder(
        SavedStateHandleHolder savedStateHandleHolder) {
      this.savedStateHandleHolder = Preconditions.checkNotNull(savedStateHandleHolder);
      return this;
    }

    @Override
    public EuPayApp_HiltComponents.ActivityRetainedC build() {
      Preconditions.checkBuilderRequirement(savedStateHandleHolder, SavedStateHandleHolder.class);
      return new ActivityRetainedCImpl(singletonCImpl, savedStateHandleHolder);
    }
  }

  private static final class ActivityCBuilder implements EuPayApp_HiltComponents.ActivityC.Builder {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private Activity activity;

    private ActivityCBuilder(SingletonCImpl singletonCImpl,
        ActivityRetainedCImpl activityRetainedCImpl) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
    }

    @Override
    public ActivityCBuilder activity(Activity activity) {
      this.activity = Preconditions.checkNotNull(activity);
      return this;
    }

    @Override
    public EuPayApp_HiltComponents.ActivityC build() {
      Preconditions.checkBuilderRequirement(activity, Activity.class);
      return new ActivityCImpl(singletonCImpl, activityRetainedCImpl, activity);
    }
  }

  private static final class FragmentCBuilder implements EuPayApp_HiltComponents.FragmentC.Builder {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ActivityCImpl activityCImpl;

    private Fragment fragment;

    private FragmentCBuilder(SingletonCImpl singletonCImpl,
        ActivityRetainedCImpl activityRetainedCImpl, ActivityCImpl activityCImpl) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
      this.activityCImpl = activityCImpl;
    }

    @Override
    public FragmentCBuilder fragment(Fragment fragment) {
      this.fragment = Preconditions.checkNotNull(fragment);
      return this;
    }

    @Override
    public EuPayApp_HiltComponents.FragmentC build() {
      Preconditions.checkBuilderRequirement(fragment, Fragment.class);
      return new FragmentCImpl(singletonCImpl, activityRetainedCImpl, activityCImpl, fragment);
    }
  }

  private static final class ViewWithFragmentCBuilder implements EuPayApp_HiltComponents.ViewWithFragmentC.Builder {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ActivityCImpl activityCImpl;

    private final FragmentCImpl fragmentCImpl;

    private View view;

    private ViewWithFragmentCBuilder(SingletonCImpl singletonCImpl,
        ActivityRetainedCImpl activityRetainedCImpl, ActivityCImpl activityCImpl,
        FragmentCImpl fragmentCImpl) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
      this.activityCImpl = activityCImpl;
      this.fragmentCImpl = fragmentCImpl;
    }

    @Override
    public ViewWithFragmentCBuilder view(View view) {
      this.view = Preconditions.checkNotNull(view);
      return this;
    }

    @Override
    public EuPayApp_HiltComponents.ViewWithFragmentC build() {
      Preconditions.checkBuilderRequirement(view, View.class);
      return new ViewWithFragmentCImpl(singletonCImpl, activityRetainedCImpl, activityCImpl, fragmentCImpl, view);
    }
  }

  private static final class ViewCBuilder implements EuPayApp_HiltComponents.ViewC.Builder {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ActivityCImpl activityCImpl;

    private View view;

    private ViewCBuilder(SingletonCImpl singletonCImpl, ActivityRetainedCImpl activityRetainedCImpl,
        ActivityCImpl activityCImpl) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
      this.activityCImpl = activityCImpl;
    }

    @Override
    public ViewCBuilder view(View view) {
      this.view = Preconditions.checkNotNull(view);
      return this;
    }

    @Override
    public EuPayApp_HiltComponents.ViewC build() {
      Preconditions.checkBuilderRequirement(view, View.class);
      return new ViewCImpl(singletonCImpl, activityRetainedCImpl, activityCImpl, view);
    }
  }

  private static final class ViewModelCBuilder implements EuPayApp_HiltComponents.ViewModelC.Builder {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private SavedStateHandle savedStateHandle;

    private ViewModelLifecycle viewModelLifecycle;

    private ViewModelCBuilder(SingletonCImpl singletonCImpl,
        ActivityRetainedCImpl activityRetainedCImpl) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
    }

    @Override
    public ViewModelCBuilder savedStateHandle(SavedStateHandle handle) {
      this.savedStateHandle = Preconditions.checkNotNull(handle);
      return this;
    }

    @Override
    public ViewModelCBuilder viewModelLifecycle(ViewModelLifecycle viewModelLifecycle) {
      this.viewModelLifecycle = Preconditions.checkNotNull(viewModelLifecycle);
      return this;
    }

    @Override
    public EuPayApp_HiltComponents.ViewModelC build() {
      Preconditions.checkBuilderRequirement(savedStateHandle, SavedStateHandle.class);
      Preconditions.checkBuilderRequirement(viewModelLifecycle, ViewModelLifecycle.class);
      return new ViewModelCImpl(singletonCImpl, activityRetainedCImpl, savedStateHandle, viewModelLifecycle);
    }
  }

  private static final class ServiceCBuilder implements EuPayApp_HiltComponents.ServiceC.Builder {
    private final SingletonCImpl singletonCImpl;

    private Service service;

    private ServiceCBuilder(SingletonCImpl singletonCImpl) {
      this.singletonCImpl = singletonCImpl;
    }

    @Override
    public ServiceCBuilder service(Service service) {
      this.service = Preconditions.checkNotNull(service);
      return this;
    }

    @Override
    public EuPayApp_HiltComponents.ServiceC build() {
      Preconditions.checkBuilderRequirement(service, Service.class);
      return new ServiceCImpl(singletonCImpl, service);
    }
  }

  private static final class ViewWithFragmentCImpl extends EuPayApp_HiltComponents.ViewWithFragmentC {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ActivityCImpl activityCImpl;

    private final FragmentCImpl fragmentCImpl;

    private final ViewWithFragmentCImpl viewWithFragmentCImpl = this;

    ViewWithFragmentCImpl(SingletonCImpl singletonCImpl,
        ActivityRetainedCImpl activityRetainedCImpl, ActivityCImpl activityCImpl,
        FragmentCImpl fragmentCImpl, View viewParam) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
      this.activityCImpl = activityCImpl;
      this.fragmentCImpl = fragmentCImpl;


    }
  }

  private static final class FragmentCImpl extends EuPayApp_HiltComponents.FragmentC {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ActivityCImpl activityCImpl;

    private final FragmentCImpl fragmentCImpl = this;

    FragmentCImpl(SingletonCImpl singletonCImpl, ActivityRetainedCImpl activityRetainedCImpl,
        ActivityCImpl activityCImpl, Fragment fragmentParam) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
      this.activityCImpl = activityCImpl;


    }

    @Override
    public DefaultViewModelFactories.InternalFactoryFactory getHiltInternalFactoryFactory() {
      return activityCImpl.getHiltInternalFactoryFactory();
    }

    @Override
    public ViewWithFragmentComponentBuilder viewWithFragmentComponentBuilder() {
      return new ViewWithFragmentCBuilder(singletonCImpl, activityRetainedCImpl, activityCImpl, fragmentCImpl);
    }
  }

  private static final class ViewCImpl extends EuPayApp_HiltComponents.ViewC {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ActivityCImpl activityCImpl;

    private final ViewCImpl viewCImpl = this;

    ViewCImpl(SingletonCImpl singletonCImpl, ActivityRetainedCImpl activityRetainedCImpl,
        ActivityCImpl activityCImpl, View viewParam) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
      this.activityCImpl = activityCImpl;


    }
  }

  private static final class ActivityCImpl extends EuPayApp_HiltComponents.ActivityC {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ActivityCImpl activityCImpl = this;

    ActivityCImpl(SingletonCImpl singletonCImpl, ActivityRetainedCImpl activityRetainedCImpl,
        Activity activityParam) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;


    }

    @Override
    public DefaultViewModelFactories.InternalFactoryFactory getHiltInternalFactoryFactory() {
      return DefaultViewModelFactories_InternalFactoryFactory_Factory.newInstance(getViewModelKeys(), new ViewModelCBuilder(singletonCImpl, activityRetainedCImpl));
    }

    @Override
    public Map<Class<?>, Boolean> getViewModelKeys() {
      return LazyClassKeyMap.<Boolean>of(MapBuilder.<String, Boolean>newMapBuilder(7).put(AuthViewModel_HiltModules_KeyModule_Provide_LazyMapKey.lazyClassKeyName, AuthViewModel_HiltModules.KeyModule.provide()).put(CardsViewModel_HiltModules_KeyModule_Provide_LazyMapKey.lazyClassKeyName, CardsViewModel_HiltModules.KeyModule.provide()).put(HomeViewModel_HiltModules_KeyModule_Provide_LazyMapKey.lazyClassKeyName, HomeViewModel_HiltModules.KeyModule.provide()).put(PayViewModel_HiltModules_KeyModule_Provide_LazyMapKey.lazyClassKeyName, PayViewModel_HiltModules.KeyModule.provide()).put(SendViewModel_HiltModules_KeyModule_Provide_LazyMapKey.lazyClassKeyName, SendViewModel_HiltModules.KeyModule.provide()).put(SettingsViewModel_HiltModules_KeyModule_Provide_LazyMapKey.lazyClassKeyName, SettingsViewModel_HiltModules.KeyModule.provide()).put(TopUpViewModel_HiltModules_KeyModule_Provide_LazyMapKey.lazyClassKeyName, TopUpViewModel_HiltModules.KeyModule.provide()).build());
    }

    @Override
    public ViewModelComponentBuilder getViewModelComponentBuilder() {
      return new ViewModelCBuilder(singletonCImpl, activityRetainedCImpl);
    }

    @Override
    public FragmentComponentBuilder fragmentComponentBuilder() {
      return new FragmentCBuilder(singletonCImpl, activityRetainedCImpl, activityCImpl);
    }

    @Override
    public ViewComponentBuilder viewComponentBuilder() {
      return new ViewCBuilder(singletonCImpl, activityRetainedCImpl, activityCImpl);
    }

    @Override
    public void injectMainActivity(MainActivity arg0) {
      injectMainActivity2(arg0);
    }

    private MainActivity injectMainActivity2(MainActivity instance) {
      MainActivity_MembersInjector.injectTokenRepository(instance, singletonCImpl.provideTokenRepositoryProvider.get());
      return instance;
    }
  }

  private static final class ViewModelCImpl extends EuPayApp_HiltComponents.ViewModelC {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ViewModelCImpl viewModelCImpl = this;

    Provider<AuthViewModel> authViewModelProvider;

    Provider<CardsViewModel> cardsViewModelProvider;

    Provider<HomeViewModel> homeViewModelProvider;

    Provider<PayViewModel> payViewModelProvider;

    Provider<SendViewModel> sendViewModelProvider;

    Provider<SettingsViewModel> settingsViewModelProvider;

    Provider<TopUpViewModel> topUpViewModelProvider;

    ViewModelCImpl(SingletonCImpl singletonCImpl, ActivityRetainedCImpl activityRetainedCImpl,
        SavedStateHandle savedStateHandleParam, ViewModelLifecycle viewModelLifecycleParam) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;

      initialize(savedStateHandleParam, viewModelLifecycleParam);

    }

    @SuppressWarnings("unchecked")
    private void initialize(final SavedStateHandle savedStateHandleParam,
        final ViewModelLifecycle viewModelLifecycleParam) {
      this.authViewModelProvider = new SwitchingProvider<>(singletonCImpl, activityRetainedCImpl, viewModelCImpl, 0);
      this.cardsViewModelProvider = new SwitchingProvider<>(singletonCImpl, activityRetainedCImpl, viewModelCImpl, 1);
      this.homeViewModelProvider = new SwitchingProvider<>(singletonCImpl, activityRetainedCImpl, viewModelCImpl, 2);
      this.payViewModelProvider = new SwitchingProvider<>(singletonCImpl, activityRetainedCImpl, viewModelCImpl, 3);
      this.sendViewModelProvider = new SwitchingProvider<>(singletonCImpl, activityRetainedCImpl, viewModelCImpl, 4);
      this.settingsViewModelProvider = new SwitchingProvider<>(singletonCImpl, activityRetainedCImpl, viewModelCImpl, 5);
      this.topUpViewModelProvider = new SwitchingProvider<>(singletonCImpl, activityRetainedCImpl, viewModelCImpl, 6);
    }

    @Override
    public Map<Class<?>, javax.inject.Provider<ViewModel>> getHiltViewModelMap() {
      return LazyClassKeyMap.<javax.inject.Provider<ViewModel>>of(MapBuilder.<String, javax.inject.Provider<ViewModel>>newMapBuilder(7).put(AuthViewModel_HiltModules_BindsModule_Binds_LazyMapKey.lazyClassKeyName, ((Provider) (authViewModelProvider))).put(CardsViewModel_HiltModules_BindsModule_Binds_LazyMapKey.lazyClassKeyName, ((Provider) (cardsViewModelProvider))).put(HomeViewModel_HiltModules_BindsModule_Binds_LazyMapKey.lazyClassKeyName, ((Provider) (homeViewModelProvider))).put(PayViewModel_HiltModules_BindsModule_Binds_LazyMapKey.lazyClassKeyName, ((Provider) (payViewModelProvider))).put(SendViewModel_HiltModules_BindsModule_Binds_LazyMapKey.lazyClassKeyName, ((Provider) (sendViewModelProvider))).put(SettingsViewModel_HiltModules_BindsModule_Binds_LazyMapKey.lazyClassKeyName, ((Provider) (settingsViewModelProvider))).put(TopUpViewModel_HiltModules_BindsModule_Binds_LazyMapKey.lazyClassKeyName, ((Provider) (topUpViewModelProvider))).build());
    }

    @Override
    public Map<Class<?>, Object> getHiltViewModelAssistedMap() {
      return Collections.<Class<?>, Object>emptyMap();
    }

    private static final class SwitchingProvider<T> implements Provider<T> {
      private final SingletonCImpl singletonCImpl;

      private final ActivityRetainedCImpl activityRetainedCImpl;

      private final ViewModelCImpl viewModelCImpl;

      private final int id;

      SwitchingProvider(SingletonCImpl singletonCImpl, ActivityRetainedCImpl activityRetainedCImpl,
          ViewModelCImpl viewModelCImpl, int id) {
        this.singletonCImpl = singletonCImpl;
        this.activityRetainedCImpl = activityRetainedCImpl;
        this.viewModelCImpl = viewModelCImpl;
        this.id = id;
      }

      @SuppressWarnings("unchecked")
      @Override
      public T get() {
        switch (id) {
          case 0: // nl.delaparra_services.apps.eupay.ui.viewmodel.AuthViewModel
          return (T) new AuthViewModel(singletonCImpl.provideAuthServiceProvider.get(), singletonCImpl.providePasskeyServiceProvider.get(), singletonCImpl.provideGsonProvider.get());

          case 1: // nl.delaparra_services.apps.eupay.ui.viewmodel.CardsViewModel
          return (T) new CardsViewModel(singletonCImpl.provideCardServiceProvider.get());

          case 2: // nl.delaparra_services.apps.eupay.ui.viewmodel.HomeViewModel
          return (T) new HomeViewModel(singletonCImpl.provideApiProvider.get());

          case 3: // nl.delaparra_services.apps.eupay.ui.viewmodel.PayViewModel
          return (T) new PayViewModel(singletonCImpl.providePaymentServiceProvider.get(), singletonCImpl.provideCardServiceProvider.get(), ApplicationContextModule_ProvideApplicationFactory.provideApplication(singletonCImpl.applicationContextModule));

          case 4: // nl.delaparra_services.apps.eupay.ui.viewmodel.SendViewModel
          return (T) new SendViewModel(singletonCImpl.p2PServiceProvider.get());

          case 5: // nl.delaparra_services.apps.eupay.ui.viewmodel.SettingsViewModel
          return (T) new SettingsViewModel(singletonCImpl.provideAuthServiceProvider.get(), singletonCImpl.provideApiProvider.get());

          case 6: // nl.delaparra_services.apps.eupay.ui.viewmodel.TopUpViewModel
          return (T) new TopUpViewModel(singletonCImpl.topUpServiceProvider.get());

          default: throw new AssertionError(id);
        }
      }
    }
  }

  private static final class ActivityRetainedCImpl extends EuPayApp_HiltComponents.ActivityRetainedC {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl = this;

    Provider<ActivityRetainedLifecycle> provideActivityRetainedLifecycleProvider;

    ActivityRetainedCImpl(SingletonCImpl singletonCImpl,
        SavedStateHandleHolder savedStateHandleHolderParam) {
      this.singletonCImpl = singletonCImpl;

      initialize(savedStateHandleHolderParam);

    }

    @SuppressWarnings("unchecked")
    private void initialize(final SavedStateHandleHolder savedStateHandleHolderParam) {
      this.provideActivityRetainedLifecycleProvider = DoubleCheck.provider(new SwitchingProvider<ActivityRetainedLifecycle>(singletonCImpl, activityRetainedCImpl, 0));
    }

    @Override
    public ActivityComponentBuilder activityComponentBuilder() {
      return new ActivityCBuilder(singletonCImpl, activityRetainedCImpl);
    }

    @Override
    public ActivityRetainedLifecycle getActivityRetainedLifecycle() {
      return provideActivityRetainedLifecycleProvider.get();
    }

    private static final class SwitchingProvider<T> implements Provider<T> {
      private final SingletonCImpl singletonCImpl;

      private final ActivityRetainedCImpl activityRetainedCImpl;

      private final int id;

      SwitchingProvider(SingletonCImpl singletonCImpl, ActivityRetainedCImpl activityRetainedCImpl,
          int id) {
        this.singletonCImpl = singletonCImpl;
        this.activityRetainedCImpl = activityRetainedCImpl;
        this.id = id;
      }

      @SuppressWarnings("unchecked")
      @Override
      public T get() {
        switch (id) {
          case 0: // dagger.hilt.android.ActivityRetainedLifecycle
          return (T) ActivityRetainedComponentManager_LifecycleModule_ProvideActivityRetainedLifecycleFactory.provideActivityRetainedLifecycle();

          default: throw new AssertionError(id);
        }
      }
    }
  }

  private static final class ServiceCImpl extends EuPayApp_HiltComponents.ServiceC {
    private final SingletonCImpl singletonCImpl;

    private final ServiceCImpl serviceCImpl = this;

    ServiceCImpl(SingletonCImpl singletonCImpl, Service serviceParam) {
      this.singletonCImpl = singletonCImpl;


    }
  }

  private static final class SingletonCImpl extends EuPayApp_HiltComponents.SingletonC {
    private final ApplicationContextModule applicationContextModule;

    private final SingletonCImpl singletonCImpl = this;

    Provider<SharedPreferences> provideEncryptedPrefsProvider;

    Provider<TokenRepository> provideTokenRepositoryProvider;

    Provider<OkHttpClient> provideOkHttpProvider;

    Provider<Retrofit> provideRetrofitProvider;

    Provider<EuPayApi> provideApiProvider;

    Provider<Gson> provideGsonProvider;

    Provider<AuthService> provideAuthServiceProvider;

    Provider<CredentialManager> provideCredentialManagerProvider;

    Provider<PasskeyService> providePasskeyServiceProvider;

    Provider<CardService> provideCardServiceProvider;

    Provider<PaymentService> providePaymentServiceProvider;

    Provider<ClientKeyManager> provideClientKeyManagerProvider;

    Provider<P2PService> p2PServiceProvider;

    Provider<TopUpService> topUpServiceProvider;

    SingletonCImpl(ApplicationContextModule applicationContextModuleParam) {
      this.applicationContextModule = applicationContextModuleParam;
      initialize(applicationContextModuleParam);

    }

    @SuppressWarnings("unchecked")
    private void initialize(final ApplicationContextModule applicationContextModuleParam) {
      this.provideEncryptedPrefsProvider = DoubleCheck.provider(new SwitchingProvider<SharedPreferences>(singletonCImpl, 1));
      this.provideTokenRepositoryProvider = DoubleCheck.provider(new SwitchingProvider<TokenRepository>(singletonCImpl, 0));
      this.provideOkHttpProvider = DoubleCheck.provider(new SwitchingProvider<OkHttpClient>(singletonCImpl, 5));
      this.provideRetrofitProvider = DoubleCheck.provider(new SwitchingProvider<Retrofit>(singletonCImpl, 4));
      this.provideApiProvider = DoubleCheck.provider(new SwitchingProvider<EuPayApi>(singletonCImpl, 3));
      this.provideGsonProvider = DoubleCheck.provider(new SwitchingProvider<Gson>(singletonCImpl, 6));
      this.provideAuthServiceProvider = DoubleCheck.provider(new SwitchingProvider<AuthService>(singletonCImpl, 2));
      this.provideCredentialManagerProvider = DoubleCheck.provider(new SwitchingProvider<CredentialManager>(singletonCImpl, 8));
      this.providePasskeyServiceProvider = DoubleCheck.provider(new SwitchingProvider<PasskeyService>(singletonCImpl, 7));
      this.provideCardServiceProvider = DoubleCheck.provider(new SwitchingProvider<CardService>(singletonCImpl, 9));
      this.providePaymentServiceProvider = DoubleCheck.provider(new SwitchingProvider<PaymentService>(singletonCImpl, 10));
      this.provideClientKeyManagerProvider = DoubleCheck.provider(new SwitchingProvider<ClientKeyManager>(singletonCImpl, 12));
      this.p2PServiceProvider = DoubleCheck.provider(new SwitchingProvider<P2PService>(singletonCImpl, 11));
      this.topUpServiceProvider = DoubleCheck.provider(new SwitchingProvider<TopUpService>(singletonCImpl, 13));
    }

    @Override
    public Set<Boolean> getDisableFragmentGetContextFix() {
      return Collections.<Boolean>emptySet();
    }

    @Override
    public ActivityRetainedComponentBuilder retainedComponentBuilder() {
      return new ActivityRetainedCBuilder(singletonCImpl);
    }

    @Override
    public ServiceComponentBuilder serviceComponentBuilder() {
      return new ServiceCBuilder(singletonCImpl);
    }

    @Override
    public void injectEuPayApp(EuPayApp euPayApp) {
    }

    private static final class SwitchingProvider<T> implements Provider<T> {
      private final SingletonCImpl singletonCImpl;

      private final int id;

      SwitchingProvider(SingletonCImpl singletonCImpl, int id) {
        this.singletonCImpl = singletonCImpl;
        this.id = id;
      }

      @SuppressWarnings("unchecked")
      @Override
      public T get() {
        switch (id) {
          case 0: // nl.delaparra_services.apps.eupay.repository.TokenRepository
          return (T) AppModule_ProvideTokenRepositoryFactory.provideTokenRepository(singletonCImpl.provideEncryptedPrefsProvider.get());

          case 1: // android.content.SharedPreferences
          return (T) AppModule_ProvideEncryptedPrefsFactory.provideEncryptedPrefs(ApplicationContextModule_ProvideContextFactory.provideContext(singletonCImpl.applicationContextModule));

          case 2: // nl.delaparra_services.apps.eupay.service.AuthService
          return (T) AppModule_ProvideAuthServiceFactory.provideAuthService(singletonCImpl.provideApiProvider.get(), singletonCImpl.provideTokenRepositoryProvider.get(), singletonCImpl.provideGsonProvider.get());

          case 3: // nl.delaparra_services.apps.eupay.api.EuPayApi
          return (T) AppModule_ProvideApiFactory.provideApi(singletonCImpl.provideRetrofitProvider.get());

          case 4: // retrofit2.Retrofit
          return (T) AppModule_ProvideRetrofitFactory.provideRetrofit(singletonCImpl.provideOkHttpProvider.get());

          case 5: // okhttp3.OkHttpClient
          return (T) AppModule_ProvideOkHttpFactory.provideOkHttp(singletonCImpl.provideTokenRepositoryProvider.get());

          case 6: // com.google.gson.Gson
          return (T) AppModule_ProvideGsonFactory.provideGson();

          case 7: // nl.delaparra_services.apps.eupay.service.PasskeyService
          return (T) AppModule_ProvidePasskeyServiceFactory.providePasskeyService(singletonCImpl.provideCredentialManagerProvider.get());

          case 8: // androidx.credentials.CredentialManager
          return (T) AppModule_ProvideCredentialManagerFactory.provideCredentialManager(ApplicationContextModule_ProvideContextFactory.provideContext(singletonCImpl.applicationContextModule));

          case 9: // nl.delaparra_services.apps.eupay.service.CardService
          return (T) AppModule_ProvideCardServiceFactory.provideCardService(singletonCImpl.provideApiProvider.get());

          case 10: // nl.delaparra_services.apps.eupay.service.PaymentService
          return (T) AppModule_ProvidePaymentServiceFactory.providePaymentService(singletonCImpl.provideApiProvider.get());

          case 11: // nl.delaparra_services.apps.eupay.service.P2PService
          return (T) new P2PService(singletonCImpl.provideApiProvider.get(), singletonCImpl.provideClientKeyManagerProvider.get());

          case 12: // nl.delaparra_services.apps.eupay.crypto.ClientKeyManager
          return (T) AppModule_ProvideClientKeyManagerFactory.provideClientKeyManager();

          case 13: // nl.delaparra_services.apps.eupay.service.TopUpService
          return (T) new TopUpService(singletonCImpl.provideApiProvider.get());

          default: throw new AssertionError(id);
        }
      }
    }
  }
}
