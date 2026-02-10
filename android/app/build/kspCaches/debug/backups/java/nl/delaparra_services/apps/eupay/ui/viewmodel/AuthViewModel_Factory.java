package nl.delaparra_services.apps.eupay.ui.viewmodel;

import com.google.gson.Gson;
import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.repository.TokenRepository;
import nl.delaparra_services.apps.eupay.service.AuthService;
import nl.delaparra_services.apps.eupay.service.PasskeyService;

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
public final class AuthViewModel_Factory implements Factory<AuthViewModel> {
  private final Provider<AuthService> authServiceProvider;

  private final Provider<PasskeyService> passkeyServiceProvider;

  private final Provider<TokenRepository> tokenRepositoryProvider;

  private final Provider<Gson> gsonProvider;

  public AuthViewModel_Factory(Provider<AuthService> authServiceProvider,
      Provider<PasskeyService> passkeyServiceProvider,
      Provider<TokenRepository> tokenRepositoryProvider, Provider<Gson> gsonProvider) {
    this.authServiceProvider = authServiceProvider;
    this.passkeyServiceProvider = passkeyServiceProvider;
    this.tokenRepositoryProvider = tokenRepositoryProvider;
    this.gsonProvider = gsonProvider;
  }

  @Override
  public AuthViewModel get() {
    return newInstance(authServiceProvider.get(), passkeyServiceProvider.get(), tokenRepositoryProvider.get(), gsonProvider.get());
  }

  public static AuthViewModel_Factory create(Provider<AuthService> authServiceProvider,
      Provider<PasskeyService> passkeyServiceProvider,
      Provider<TokenRepository> tokenRepositoryProvider, Provider<Gson> gsonProvider) {
    return new AuthViewModel_Factory(authServiceProvider, passkeyServiceProvider, tokenRepositoryProvider, gsonProvider);
  }

  public static AuthViewModel newInstance(AuthService authService, PasskeyService passkeyService,
      TokenRepository tokenRepository, Gson gson) {
    return new AuthViewModel(authService, passkeyService, tokenRepository, gson);
  }
}
