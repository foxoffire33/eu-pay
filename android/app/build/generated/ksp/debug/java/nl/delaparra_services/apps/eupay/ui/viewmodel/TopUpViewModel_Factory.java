package nl.delaparra_services.apps.eupay.ui.viewmodel;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.service.TopUpService;

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
public final class TopUpViewModel_Factory implements Factory<TopUpViewModel> {
  private final Provider<TopUpService> topUpServiceProvider;

  public TopUpViewModel_Factory(Provider<TopUpService> topUpServiceProvider) {
    this.topUpServiceProvider = topUpServiceProvider;
  }

  @Override
  public TopUpViewModel get() {
    return newInstance(topUpServiceProvider.get());
  }

  public static TopUpViewModel_Factory create(Provider<TopUpService> topUpServiceProvider) {
    return new TopUpViewModel_Factory(topUpServiceProvider);
  }

  public static TopUpViewModel newInstance(TopUpService topUpService) {
    return new TopUpViewModel(topUpService);
  }
}
