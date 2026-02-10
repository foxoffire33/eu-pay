package nl.delaparra_services.apps.eupay.di;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Preconditions;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.repository.TokenRepository;
import okhttp3.OkHttpClient;

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
public final class AppModule_ProvideOkHttpFactory implements Factory<OkHttpClient> {
  private final Provider<TokenRepository> tokenRepoProvider;

  public AppModule_ProvideOkHttpFactory(Provider<TokenRepository> tokenRepoProvider) {
    this.tokenRepoProvider = tokenRepoProvider;
  }

  @Override
  public OkHttpClient get() {
    return provideOkHttp(tokenRepoProvider.get());
  }

  public static AppModule_ProvideOkHttpFactory create(Provider<TokenRepository> tokenRepoProvider) {
    return new AppModule_ProvideOkHttpFactory(tokenRepoProvider);
  }

  public static OkHttpClient provideOkHttp(TokenRepository tokenRepo) {
    return Preconditions.checkNotNullFromProvides(AppModule.INSTANCE.provideOkHttp(tokenRepo));
  }
}
