import { Layout } from './components/Layout';
import { Hero } from './sections/Hero';
import { Features } from './sections/Features';
import { HowItWorks } from './sections/HowItWorks';
import { Security } from './sections/Security';
import { SupportedBanks } from './sections/SupportedBanks';
import { CallToAction } from './sections/CallToAction';

export function App() {
  return (
    <Layout>
      <Hero />
      <Features />
      <HowItWorks />
      <Security />
      <SupportedBanks />
      <CallToAction />
    </Layout>
  );
}
