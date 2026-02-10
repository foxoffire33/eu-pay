package nl.delaparra_services.apps.eupay.di;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Preconditions;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.api.EuPayApi;
import nl.delaparra_services.apps.eupay.service.CardService;

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
public final class AppModule_ProvideCardServiceFactory implements Factory<CardService> {
  private final Provider<EuPayApi> apiProvider;

  public AppModule_ProvideCardServiceFactory(Provider<EuPayApi> apiProvider) {
    this.apiProvider = apiProvider;
  }

  @Override
  public CardService get() {
    return provideCardService(apiProvider.get());
  }

  public static AppModule_ProvideCardServiceFactory create(Provider<EuPayApi> apiProvider) {
    return new AppModule_ProvideCardServiceFactory(apiProvider);
  }

  public static CardService provideCardService(EuPayApi api) {
    return Preconditions.checkNotNullFromProvides(AppModule.INSTANCE.provideCardService(api));
  }
}
