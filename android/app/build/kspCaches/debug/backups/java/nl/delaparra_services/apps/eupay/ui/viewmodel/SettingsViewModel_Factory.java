package nl.delaparra_services.apps.eupay.ui.viewmodel;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.api.EuPayApi;
import nl.delaparra_services.apps.eupay.service.AuthService;

@ScopeMetadata
@QualifierMetadata
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
public final class SettingsViewModel_Factory implements Factory<SettingsViewModel> {
  private final Provider<AuthService> authServiceProvider;

  private final Provider<EuPayApi> apiProvider;

  public SettingsViewModel_Factory(Provider<AuthService> authServiceProvider,
      Provider<EuPayApi> apiProvider) {
    this.authServiceProvider = authServiceProvider;
    this.apiProvider = apiProvider;
  }

  @Override
  public SettingsViewModel get() {
    return newInstance(authServiceProvider.get(), apiProvider.get());
  }

  public static SettingsViewModel_Factory create(Provider<AuthService> authServiceProvider,
      Provider<EuPayApi> apiProvider) {
    return new SettingsViewModel_Factory(authServiceProvider, apiProvider);
  }

  public static SettingsViewModel newInstance(AuthService authService, EuPayApi api) {
    return new SettingsViewModel(authService, api);
  }
}
