package nl.delaparra_services.apps.eupay.di;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Preconditions;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.api.EuPayApi;
import nl.delaparra_services.apps.eupay.service.PaymentService;

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
public final class AppModule_ProvidePaymentServiceFactory implements Factory<PaymentService> {
  private final Provider<EuPayApi> apiProvider;

  public AppModule_ProvidePaymentServiceFactory(Provider<EuPayApi> apiProvider) {
    this.apiProvider = apiProvider;
  }

  @Override
  public PaymentService get() {
    return providePaymentService(apiProvider.get());
  }

  public static AppModule_ProvidePaymentServiceFactory create(Provider<EuPayApi> apiProvider) {
    return new AppModule_ProvidePaymentServiceFactory(apiProvider);
  }

  public static PaymentService providePaymentService(EuPayApi api) {
    return Preconditions.checkNotNullFromProvides(AppModule.INSTANCE.providePaymentService(api));
  }
}
