package nl.delaparra_services.apps.eupay.ui.viewmodel;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.api.EuPayApi;

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
public final class HomeViewModel_Factory implements Factory<HomeViewModel> {
  private final Provider<EuPayApi> apiProvider;

  public HomeViewModel_Factory(Provider<EuPayApi> apiProvider) {
    this.apiProvider = apiProvider;
  }

  @Override
  public HomeViewModel get() {
    return newInstance(apiProvider.get());
  }

  public static HomeViewModel_Factory create(Provider<EuPayApi> apiProvider) {
    return new HomeViewModel_Factory(apiProvider);
  }

  public static HomeViewModel newInstance(EuPayApi api) {
    return new HomeViewModel(api);
  }
}
