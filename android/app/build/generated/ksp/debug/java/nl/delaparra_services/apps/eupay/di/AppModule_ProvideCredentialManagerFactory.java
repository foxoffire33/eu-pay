package nl.delaparra_services.apps.eupay.di;

import android.content.Context;
import androidx.credentials.CredentialManager;
import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Preconditions;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;

@ScopeMetadata("javax.inject.Singleton")
@QualifierMetadata("dagger.hilt.android.qualifiers.ApplicationContext")
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
public final class AppModule_ProvideCredentialManagerFactory implements Factory<CredentialManager> {
  private final Provider<Context> ctxProvider;

  public AppModule_ProvideCredentialManagerFactory(Provider<Context> ctxProvider) {
    this.ctxProvider = ctxProvider;
  }

  @Override
  public CredentialManager get() {
    return provideCredentialManager(ctxProvider.get());
  }

  public static AppModule_ProvideCredentialManagerFactory create(Provider<Context> ctxProvider) {
    return new AppModule_ProvideCredentialManagerFactory(ctxProvider);
  }

  public static CredentialManager provideCredentialManager(Context ctx) {
    return Preconditions.checkNotNullFromProvides(AppModule.INSTANCE.provideCredentialManager(ctx));
  }
}
