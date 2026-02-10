package nl.delaparra_services.apps.eupay.service;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.api.EuPayApi;

@ScopeMetadata("javax.inject.Singleton")
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
public final class TopUpService_Factory implements Factory<TopUpService> {
  private final Provider<EuPayApi> apiProvider;

  public TopUpService_Factory(Provider<EuPayApi> apiProvider) {
    this.apiProvider = apiProvider;
  }

  @Override
  public TopUpService get() {
    return newInstance(apiProvider.get());
  }

  public static TopUpService_Factory create(Provider<EuPayApi> apiProvider) {
    return new TopUpService_Factory(apiProvider);
  }

  public static TopUpService newInstance(EuPayApi api) {
    return new TopUpService(api);
  }
}
