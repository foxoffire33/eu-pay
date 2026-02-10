package nl.delaparra_services.apps.eupay.di;

import android.content.Context;
import android.content.SharedPreferences;
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
public final class AppModule_ProvideEncryptedPrefsFactory implements Factory<SharedPreferences> {
  private final Provider<Context> ctxProvider;

  public AppModule_ProvideEncryptedPrefsFactory(Provider<Context> ctxProvider) {
    this.ctxProvider = ctxProvider;
  }

  @Override
  public SharedPreferences get() {
    return provideEncryptedPrefs(ctxProvider.get());
  }

  public static AppModule_ProvideEncryptedPrefsFactory create(Provider<Context> ctxProvider) {
    return new AppModule_ProvideEncryptedPrefsFactory(ctxProvider);
  }

  public static SharedPreferences provideEncryptedPrefs(Context ctx) {
    return Preconditions.checkNotNullFromProvides(AppModule.INSTANCE.provideEncryptedPrefs(ctx));
  }
}
