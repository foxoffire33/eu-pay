import {
  Group,
  Text,
  Button,
  Container,
  Box,
} from '@mantine/core';
import { IconBrandGithub } from '@tabler/icons-react';

export function Header() {
  return (
    <Box component="header" py="md" style={{ borderBottom: '1px solid var(--mantine-color-gray-2)' }}>
      <Container size="lg">
        <Group justify="space-between">
          <Group gap="xs">
            <Text
              fw={800}
              size="xl"
              c="eupay.6"
              component="a"
              href="/"
              style={{ textDecoration: 'none' }}
            >
              EU Pay
            </Text>
          </Group>
          <Group gap="sm">
            <Button
              component="a"
              href="https://github.com/user/eu-pay"
              variant="subtle"
              color="gray"
              leftSection={<IconBrandGithub size={18} />}
            >
              GitHub
            </Button>
            <Button
              component="a"
              href="https://github.com/user/eu-pay/releases"
              variant="filled"
            >
              Download
            </Button>
          </Group>
        </Group>
      </Container>
    </Box>
  );
}
