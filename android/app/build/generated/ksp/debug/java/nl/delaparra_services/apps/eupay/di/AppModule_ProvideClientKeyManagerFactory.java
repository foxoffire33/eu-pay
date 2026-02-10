package nl.delaparra_services.apps.eupay.di;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Preconditions;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.crypto.ClientKeyManager;

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
public final class AppModule_ProvideClientKeyManagerFactory implements Factory<ClientKeyManager> {
  @Override
  public ClientKeyManager get() {
    return provideClientKeyManager();
  }

  public static AppModule_ProvideClientKeyManagerFactory create() {
    return InstanceHolder.INSTANCE;
  }

  public static ClientKeyManager provideClientKeyManager() {
    return Preconditions.checkNotNullFromProvides(AppModule.INSTANCE.provideClientKeyManager());
  }

  private static final class InstanceHolder {
    static final AppModule_ProvideClientKeyManagerFactory INSTANCE = new AppModule_ProvideClientKeyManagerFactory();
  }
}
