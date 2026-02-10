package nl.delaparra_services.apps.eupay.ui;

import dagger.MembersInjector;
import dagger.internal.DaggerGenerated;
import dagger.internal.InjectedFieldSignature;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.repository.TokenRepository;

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
public final class MainActivity_MembersInjector implements MembersInjector<MainActivity> {
  private final Provider<TokenRepository> tokenRepositoryProvider;

  public MainActivity_MembersInjector(Provider<TokenRepository> tokenRepositoryProvider) {
    this.tokenRepositoryProvider = tokenRepositoryProvider;
  }

  public static MembersInjector<MainActivity> create(
      Provider<TokenRepository> tokenRepositoryProvider) {
    return new MainActivity_MembersInjector(tokenRepositoryProvider);
  }

  @Override
  public void injectMembers(MainActivity instance) {
    injectTokenRepository(instance, tokenRepositoryProvider.get());
  }

  @InjectedFieldSignature("nl.delaparra_services.apps.eupay.ui.MainActivity.tokenRepository")
  public static void injectTokenRepository(MainActivity instance, TokenRepository tokenRepository) {
    instance.tokenRepository = tokenRepository;
  }
}
