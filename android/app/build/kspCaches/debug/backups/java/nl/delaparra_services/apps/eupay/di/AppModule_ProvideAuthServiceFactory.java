package nl.delaparra_services.apps.eupay.di;

import com.google.gson.Gson;
import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Preconditions;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.api.EuPayApi;
import nl.delaparra_services.apps.eupay.repository.TokenRepository;
import nl.delaparra_services.apps.eupay.service.AuthService;

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
public final class AppModule_ProvideAuthServiceFactory implements Factory<AuthService> {
  private final Provider<EuPayApi> apiProvider;

  private final Provider<TokenRepository> tokenRepoProvider;

  private final Provider<Gson> gsonProvider;

  public AppModule_ProvideAuthServiceFactory(Provider<EuPayApi> apiProvider,
      Provider<TokenRepository> tokenRepoProvider, Provider<Gson> gsonProvider) {
    this.apiProvider = apiProvider;
    this.tokenRepoProvider = tokenRepoProvider;
    this.gsonProvider = gsonProvider;
  }

  @Override
  public AuthService get() {
    return provideAuthService(apiProvider.get(), tokenRepoProvider.get(), gsonProvider.get());
  }

  public static AppModule_ProvideAuthServiceFactory create(Provider<EuPayApi> apiProvider,
      Provider<TokenRepository> tokenRepoProvider, Provider<Gson> gsonProvider) {
    return new AppModule_ProvideAuthServiceFactory(apiProvider, tokenRepoProvider, gsonProvider);
  }

  public static AuthService provideAuthService(EuPayApi api, TokenRepository tokenRepo, Gson gson) {
    return Preconditions.checkNotNullFromProvides(AppModule.INSTANCE.provideAuthService(api, tokenRepo, gson));
  }
}
