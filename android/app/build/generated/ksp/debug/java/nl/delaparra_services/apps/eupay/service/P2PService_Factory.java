package nl.delaparra_services.apps.eupay.service;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.api.EuPayApi;
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
public final class P2PService_Factory implements Factory<P2PService> {
  private final Provider<EuPayApi> apiProvider;

  private final Provider<ClientKeyManager> keyManagerProvider;

  public P2PService_Factory(Provider<EuPayApi> apiProvider,
      Provider<ClientKeyManager> keyManagerProvider) {
    this.apiProvider = apiProvider;
    this.keyManagerProvider = keyManagerProvider;
  }

  @Override
  public P2PService get() {
    return newInstance(apiProvider.get(), keyManagerProvider.get());
  }

  public static P2PService_Factory create(Provider<EuPayApi> apiProvider,
      Provider<ClientKeyManager> keyManagerProvider) {
    return new P2PService_Factory(apiProvider, keyManagerProvider);
  }

  public static P2PService newInstance(EuPayApi api, ClientKeyManager keyManager) {
    return new P2PService(api, keyManager);
  }
}
