package nl.delaparra_services.apps.eupay.di;

import android.content.SharedPreferences;
import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Preconditions;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.repository.TokenRepository;

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
public final class AppModule_ProvideTokenRepositoryFactory implements Factory<TokenRepository> {
  private final Provider<SharedPreferences> prefsProvider;

  public AppModule_ProvideTokenRepositoryFactory(Provider<SharedPreferences> prefsProvider) {
    this.prefsProvider = prefsProvider;
  }

  @Override
  public TokenRepository get() {
    return provideTokenRepository(prefsProvider.get());
  }

  public static AppModule_ProvideTokenRepositoryFactory create(
      Provider<SharedPreferences> prefsProvider) {
    return new AppModule_ProvideTokenRepositoryFactory(prefsProvider);
  }

  public static TokenRepository provideTokenRepository(SharedPreferences prefs) {
    return Preconditions.checkNotNullFromProvides(AppModule.INSTANCE.provideTokenRepository(prefs));
  }
}
