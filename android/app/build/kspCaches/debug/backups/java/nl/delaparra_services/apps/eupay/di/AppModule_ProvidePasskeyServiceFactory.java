package nl.delaparra_services.apps.eupay.di;

import androidx.credentials.CredentialManager;
import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Preconditions;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.service.PasskeyService;

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
public final class AppModule_ProvidePasskeyServiceFactory implements Factory<PasskeyService> {
  private final Provider<CredentialManager> credentialManagerProvider;

  public AppModule_ProvidePasskeyServiceFactory(
      Provider<CredentialManager> credentialManagerProvider) {
    this.credentialManagerProvider = credentialManagerProvider;
  }

  @Override
  public PasskeyService get() {
    return providePasskeyService(credentialManagerProvider.get());
  }

  public static AppModule_ProvidePasskeyServiceFactory create(
      Provider<CredentialManager> credentialManagerProvider) {
    return new AppModule_ProvidePasskeyServiceFactory(credentialManagerProvider);
  }

  public static PasskeyService providePasskeyService(CredentialManager credentialManager) {
    return Preconditions.checkNotNullFromProvides(AppModule.INSTANCE.providePasskeyService(credentialManager));
  }
}
